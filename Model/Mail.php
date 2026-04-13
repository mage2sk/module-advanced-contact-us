<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Mail
{
    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function sendAdminNotification(array $data): void
    {
        try {
            $this->inlineTranslation->suspend();
            $storeId = (int) $this->storeManager->getStore()->getId();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getAdminTemplate())
                ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
                ->setTemplateVars(['data' => new \Magento\Framework\DataObject($data)])
                ->setFrom($this->config->getSenderIdentity())
                ->addTo($this->config->getRecipientEmail())
                ->setReplyTo($data['email'], $data['name'])
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->critical('Panth Contact admin email failed: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    public function sendCustomerConfirmation(array $data): void
    {
        if (!$this->config->sendConfirmation()) {
            return;
        }

        try {
            $this->inlineTranslation->suspend();
            $storeId = (int) $this->storeManager->getStore()->getId();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getCustomerTemplate())
                ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
                ->setTemplateVars([
                    'data' => new \Magento\Framework\DataObject($data),
                    'store' => $this->storeManager->getStore(),
                ])
                ->setFrom($this->config->getSenderIdentity())
                ->addTo($data['email'], $data['name'])
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->critical('Panth Contact customer email failed: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
