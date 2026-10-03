<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Shared admin controller wiring: records the redirect path and flash messages.
 */
abstract class SubmissionControllerTestCase extends TestCase
{
    protected ?string $redirectPath = null;
    protected array $messages = ['success' => [], 'error' => []];

    protected function context(array $params = []): Context
    {
        $this->redirectPath = null;
        $this->messages = ['success' => [], 'error' => []];

        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key, $default = null) => $params[$key] ?? $default
        );

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path) use (&$redirect) {
            $this->redirectPath = $path;
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messageManager = $this->createStub(ManagerInterface::class);
        $messageManager->method('addSuccessMessage')->willReturnCallback(function ($m) use (&$messageManager) {
            $this->messages['success'][] = (string) $m;
            return $messageManager;
        });
        $messageManager->method('addErrorMessage')->willReturnCallback(function ($m) use (&$messageManager) {
            $this->messages['error'][] = (string) $m;
            return $messageManager;
        });

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messageManager);

        return $context;
    }
}
