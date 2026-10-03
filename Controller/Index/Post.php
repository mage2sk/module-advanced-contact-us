<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Contact\Controller\Index\PostFactory as StockPostFactory;
use Magento\Contact\Model\ConfigInterface as StockContactConfig;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\Model\Mail;
use Panth\AdvancedContactUs\Model\Spam\ContentGuard;
use Panth\AdvancedContactUs\ViewModel\ContactForm;
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
    private ResponseInterface $response;
    private ContentGuard $contentGuard;
    private FormKeyValidator $formKeyValidator;
    private StockPostFactory $stockPostFactory;
    private StockContactConfig $stockContactConfig;
    private RemoteAddress $remoteAddress;

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
        LoggerInterface $logger,
        ResponseInterface $response,
        ContentGuard $contentGuard,
        FormKeyValidator $formKeyValidator,
        StockPostFactory $stockPostFactory,
        StockContactConfig $stockContactConfig,
        RemoteAddress $remoteAddress
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
        $this->response = $response;
        $this->contentGuard = $contentGuard;
        $this->formKeyValidator = $formKeyValidator;
        $this->stockPostFactory = $stockPostFactory;
        $this->stockContactConfig = $stockContactConfig;
        $this->remoteAddress = $remoteAddress;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        if (!$request->getParam('ajax')) {
            return null;
        }

        $message = __('Invalid Form Key. Please refresh the page.');
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(403);
        $result->setData(['success' => false, 'message' => (string) $message]);

        return new InvalidRequestException($result, [$message]);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            if (!$this->stockContactConfig->isEnabled()) {
                throw new NotFoundException(__('Page not found.'));
            }
            return $this->stockPostFactory->create()->execute();
        }

        $isAjax = (bool) $this->request->getParam('ajax');

        try {
            $post = $this->request->getPostValue();
            if (!is_array($post)) {
                $post = [];
            }
            $this->assertScalarFields($post);
            $clientIp = $this->getClientIp();

            $this->warnIfAntiSpamFieldsMissing($post);

            if ($this->config->isHoneypotEnabled() && !empty($post[ContactForm::HONEYPOT_FIELD])) {
                return $this->successResponse($isAjax);
            }

            $inspected = $this->collectInspectableValues($post);
            $spamReason = $this->contentGuard->detect($inspected, ['name', 'subject']);
            if ($spamReason !== null) {
                $this->logger->info('Panth AdvancedContactUs: submission blocked by the content guard', [
                    'reason' => $spamReason,
                    'ip' => $clientIp,
                    'sample' => $this->contentGuard->sample($inspected),
                ]);

                return $this->successResponse($isAjax);
            }

            $minTime = $this->config->getMinTime();
            if ($minTime > 0 && isset($post[ContactForm::TIMESTAMP_FIELD])
                && is_scalar($post[ContactForm::TIMESTAMP_FIELD])
            ) {
                $elapsed = time() - (int) $post[ContactForm::TIMESTAMP_FIELD];
                if ($elapsed < $minTime) {
                    return $this->successResponse($isAjax);
                }
            }

            if ($this->config->isRateLimitEnabled()) {
                if ($this->countRecent('ip_address', $clientIp) >= $this->config->getMaxPerHour()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Too many submissions. Please try again later.')
                    );
                }
            }

            $this->validate($post);

            $customFieldsData = [];
            $configuredFields = $this->config->getCustomFields();
            foreach ($configuredFields as $field) {
                $key = isset($field['key']) && is_string($field['key'])
                    ? $field['key']
                    : Config::buildCustomFieldKey((string) $field['label']);
                $value = isset($post[$key]) ? $this->normaliseCustomValue($post[$key]) : '';
                $this->validateCustomField($field, $value);
                if (isset($post[$key])) {
                    $customFieldsData[$field['label']] = $value;
                }
            }

            $submission = $this->submissionFactory->create();
            $submission->setData([
                'name' => trim((string) $post['name']),
                'email' => trim((string) $post['email']),
                'telephone' => isset($post['telephone']) ? trim((string) $post['telephone']) : null,
                'subject' => isset($post['subject']) ? trim((string) $post['subject']) : null,
                'message' => trim((string) $post['message']),
                'custom_fields' => !empty($customFieldsData) ? json_encode($customFieldsData) : null,
                'status' => 0,
                'ip_address' => $clientIp,
                'user_agent' => substr((string) $this->request->getServer('HTTP_USER_AGENT', ''), 0, 500),
                'store_id' => $this->storeManager->getStore()->getId(),
            ]);
            $this->submissionResource->save($submission);

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

            $emailData = [
                'name' => trim((string) $post['name']),
                'email' => trim((string) $post['email']),
                'telephone' => isset($post['telephone']) ? trim((string) $post['telephone']) : '',
                'subject' => isset($post['subject']) ? trim((string) $post['subject']) : 'Contact Form Submission',
                'message' => trim((string) $post['message']),
                'custom_fields' => $customFieldsData,
                'custom_fields_html' => $customFieldsHtml,
                'ip_address' => $clientIp,
                'submitted_at' => date('Y-m-d H:i:s'),
            ];

            try {
                $this->mail->sendAdminNotification($emailData);
            } catch (\Exception $e) {
                $this->logger->error('Panth Contact admin email: ' . $e->getMessage());
            }
            if ($this->config->sendConfirmation()) {
                if ($this->isConfirmationAllowed($emailData['email'], $clientIp)) {
                    try {
                        $this->mail->sendCustomerConfirmation($emailData);
                    } catch (\Exception $e) {
                        $this->logger->error('Panth Contact customer email: ' . $e->getMessage());
                    }
                } else {
                    $this->logger->info(
                        'Panth AdvancedContactUs: customer confirmation skipped by the email rate limit',
                        ['ip' => $clientIp]
                    );
                }
            }

            return $this->successResponse($isAjax);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->errorResponse($isAjax, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical('Panth Contact form error: ' . $e->getMessage());
            return $this->errorResponse($isAjax, __('An error occurred. Please try again later.'));
        }
    }

    private function getClientIp(): string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        if ($ip === '') {
            $ip = (string) $this->request->getServer('REMOTE_ADDR', '');
        }

        return substr($ip, 0, 45);
    }

    private function countRecent(string $field, string $value): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter($field, $value);
        $collection->addFieldToFilter(
            'created_at',
            ['gteq' => new Expression('DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)')]
        );

        return (int) $collection->getSize();
    }

    private function isConfirmationAllowed(string $email, string $clientIp): bool
    {
        if ($this->countRecent('email', $email) > $this->config->getConfirmationMaxPerRecipient()) {
            return false;
        }

        return $clientIp === ''
            || $this->countRecent('ip_address', $clientIp) <= $this->config->getConfirmationMaxPerIp();
    }

    private function assertScalarFields(array $post): void
    {
        foreach (['name', 'email', 'telephone', 'subject', 'message'] as $field) {
            if (isset($post[$field]) && !is_scalar($post[$field])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid form data.'));
            }
        }
    }

    private function validateCustomField(array $field, $value): void
    {
        $label = (string) $field['label'];
        $values = array_values(array_filter(
            is_array($value) ? $value : [(string) $value],
            static fn ($item) => $item !== ''
        ));

        if (!empty($field['required']) && $values === []) {
            throw new \Magento\Framework\Exception\LocalizedException(__('%1 is required.', $label));
        }

        $type = (string) ($field['type'] ?? 'text');
        $options = trim((string) ($field['options'] ?? ''));
        if ($values === [] || $options === '' || !in_array($type, ['select', 'radio'], true)) {
            return;
        }

        $allowed = array_map('trim', explode(',', $options));
        foreach ($values as $item) {
            if (!in_array($item, $allowed, true)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Please select a valid option for %1.', $label)
                );
            }
        }
    }

    private function normaliseCustomValue($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $clean[] = trim((string) $item);
                }
            }
            return $clean;
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function warnIfAntiSpamFieldsMissing(array $post): void
    {
        $honeypotOn = $this->config->isHoneypotEnabled();
        $timingOn   = $this->config->getMinTime() > 0;

        if (!$honeypotOn && !$timingOn) {
            return;
        }

        $honeypotPresent = array_key_exists(ContactForm::HONEYPOT_FIELD, $post);
        $timestampPresent = array_key_exists(ContactForm::TIMESTAMP_FIELD, $post);

        if (($honeypotOn && $honeypotPresent) || ($timingOn && $timestampPresent)) {
            return;
        }

        $this->logger->info(
            'Panth AdvancedContactUs: anti-spam fields were not present in the submission. '
            . 'A custom form template is probably missing '
            . '<?= $viewModel->getAntiSpamFieldsHtml() ?> inside the <form>. '
            . 'The honeypot and minimum-submit-time checks are being skipped; the content guard still applies.',
            ['honeypot_enabled' => $honeypotOn, 'timing_enabled' => $timingOn]
        );
    }

    private function collectInspectableValues(array $post): array
    {
        $skip = ['email', 'telephone', 'form_key', ContactForm::HONEYPOT_FIELD, ContactForm::TIMESTAMP_FIELD, 'ajax'];
        $values = [];

        foreach ($post as $key => $value) {
            $key = (string) $key;
            if (in_array($key, $skip, true) || str_starts_with($key, '_')) {
                continue;
            }
            if ($key === 'name' || $key === 'subject' || $key === 'message' || str_starts_with($key, 'custom_')) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private function validate(array $post): void
    {
        if (trim((string) ($post['name'] ?? '')) === '') {
            throw new \Magento\Framework\Exception\LocalizedException(__('Name is required.'));
        }
        if (filter_var(trim((string) ($post['email'] ?? '')), FILTER_VALIDATE_EMAIL) === false) {
            throw new \Magento\Framework\Exception\LocalizedException(__('A valid email address is required.'));
        }
        if (trim((string) ($post['message'] ?? '')) === '') {
            throw new \Magento\Framework\Exception\LocalizedException(__('Message is required.'));
        }
        if ($this->config->isPhoneRequired() && trim((string) ($post['telephone'] ?? '')) === '') {
            throw new \Magento\Framework\Exception\LocalizedException(__('Phone number is required.'));
        }
        if ($this->config->isSubjectRequired() && trim((string) ($post['subject'] ?? '')) === '') {
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
