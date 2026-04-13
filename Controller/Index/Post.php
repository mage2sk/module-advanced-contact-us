<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\Model\Mail;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Psr\Log\LoggerInterface;

class Post implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private JsonFactory $jsonFactory;
    private ManagerInterface $messageManager;
    private StoreManagerInterface $storeManager;
    private Config $config;
    private Mail $mail;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;
    private CollectionFactory $collectionFactory;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        JsonFactory $jsonFactory,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        Config $config,
        Mail $mail,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->jsonFactory = $jsonFactory;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->mail = $mail;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $isAjax = (bool) $this->request->getParam('ajax');

        try {
            $post = $this->request->getPostValue();

            // Bot Protection 1: Honeypot
            if ($this->config->isHoneypotEnabled() && !empty($post['website_url'])) {
                return $this->successResponse($isAjax); // Silent rejection
            }

            // Bot Protection 2: Time-based
            $minTime = $this->config->getMinTime();
            if ($minTime > 0 && isset($post['_timestamp'])) {
                $elapsed = time() - (int) $post['_timestamp'];
                if ($elapsed < $minTime) {
                    return $this->successResponse($isAjax); // Silent rejection
                }
            }

            // Bot Protection 3: Rate limiting
            if ($this->config->isRateLimitEnabled()) {
                $ip = $this->request->getServer('REMOTE_ADDR');
                $maxPerHour = $this->config->getMaxPerHour();
                $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

                $collection = $this->collectionFactory->create();
                $collection->addFieldToFilter('ip_address', $ip);
                $collection->addFieldToFilter('created_at', ['gteq' => $oneHourAgo]);

                if ($collection->getSize() >= $maxPerHour) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Too many submissions. Please try again later.')
                    );
                }
            }

            // Validate required fields
            $this->validate($post);

            // Collect custom fields
            $customFieldsData = [];
            $configuredFields = $this->config->getCustomFields();
            foreach ($configuredFields as $field) {
                $key = 'custom_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($field['label']));
                if (isset($post[$key])) {
                    $value = $post[$key];
                    $customFieldsData[$field['label']] = is_array($value) ? $value : trim($value);
                }
            }

            // Save to database
            $submission = $this->submissionFactory->create();
            $submission->setData([
                'name' => trim($post['name']),
                'email' => trim($post['email']),
                'telephone' => isset($post['telephone']) ? trim($post['telephone']) : null,
                'subject' => isset($post['subject']) ? trim($post['subject']) : null,
                'message' => trim($post['message']),
                'custom_fields' => !empty($customFieldsData) ? json_encode($customFieldsData) : null,
                'status' => 0,
                'ip_address' => $this->request->getServer('REMOTE_ADDR'),
                'user_agent' => substr($this->request->getServer('HTTP_USER_AGENT', ''), 0, 500),
                'store_id' => $this->storeManager->getStore()->getId(),
            ]);
            $this->submissionResource->save($submission);

            // Build custom fields HTML for email
            $customFieldsHtml = '';
            if (!empty($customFieldsData)) {
                foreach ($customFieldsData as $label => $value) {
                    $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
                    $customFieldsHtml .= '<tr>'
                        . '<td style="padding:12px 16px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;font-weight:600;color:#6B7280;font-size:14px;">'
                        . htmlspecialchars($label) . '</td>'
                        . '<td style="padding:12px 16px;border-bottom:1px solid #E5E7EB;color:#171717;font-size:14px;">'
                        . htmlspecialchars($displayValue) . '</td></tr>';
                }
            }

            // Prepare email data
            $emailData = [
                'name' => trim($post['name']),
                'email' => trim($post['email']),
                'telephone' => isset($post['telephone']) ? trim($post['telephone']) : '',
                'subject' => isset($post['subject']) ? trim($post['subject']) : 'Contact Form Submission',
                'message' => trim($post['message']),
                'custom_fields' => $customFieldsData,
                'custom_fields_html' => $customFieldsHtml,
                'ip_address' => $this->request->getServer('REMOTE_ADDR'),
                'submitted_at' => date('Y-m-d H:i:s'),
            ];

            // Queue emails via Magento's async email (if available) or send sync
            // Uses Magento's built-in async email queue when configured
            try {
                $this->mail->sendAdminNotification($emailData);
            } catch (\Exception $e) {
                $this->logger->error('Panth Contact admin email: ' . $e->getMessage());
            }
            try {
                $this->mail->sendCustomerConfirmation($emailData);
            } catch (\Exception $e) {
                $this->logger->error('Panth Contact customer email: ' . $e->getMessage());
            }

            return $this->successResponse($isAjax);

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->errorResponse($isAjax, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical('Panth Contact form error: ' . $e->getMessage());
            return $this->errorResponse($isAjax, __('An error occurred. Please try again later.'));
        }
    }

    private function validate(array $post): void
    {
        if (empty(trim($post['name'] ?? ''))) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Name is required.'));
        }
        if (empty(trim($post['email'] ?? '')) || strpos($post['email'], '@') === false) {
            throw new \Magento\Framework\Exception\LocalizedException(__('A valid email address is required.'));
        }
        if (empty(trim($post['message'] ?? ''))) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Message is required.'));
        }
        if ($this->config->isPhoneRequired() && empty(trim($post['telephone'] ?? ''))) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Phone number is required.'));
        }
        if ($this->config->isSubjectRequired() && empty(trim($post['subject'] ?? ''))) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Subject is required.'));
        }
    }

    private function successResponse(bool $isAjax)
    {
        $message = $this->config->getSuccessMessage();
        if ($isAjax) {
            $result = $this->jsonFactory->create();
            return $result->setData(['success' => true, 'message' => $message]);
        }
        $this->messageManager->addSuccessMessage($message);
        return $this->redirectFactory->create()->setPath('contact');
    }

    private function errorResponse(bool $isAjax, $message)
    {
        if ($isAjax) {
            $result = $this->jsonFactory->create();
            return $result->setData(['success' => false, 'message' => (string) $message]);
        }
        $this->messageManager->addErrorMessage($message);
        return $this->redirectFactory->create()->setPath('contact');
    }
}
