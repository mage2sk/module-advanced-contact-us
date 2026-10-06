<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Index;

use Magento\Contact\Controller\Index\Post as StockPost;
use Magento\Contact\Controller\Index\PostFactory as StockPostFactory;
use Magento\Contact\Model\ConfigInterface as StockContactConfig;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedContactUs\Controller\Index\Post;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\Model\Mail;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\Collection;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Panth\AdvancedContactUs\Model\Spam\ContentGuard;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\ViewModel\ContactForm;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostTest extends TestCase
{
    private const VALID = [
        'name' => ' Ann Lee ',
        'email' => ' ann@shop.test ',
        'message' => ' Where is my order? ',
    ];

    private array $json = [];
    private ?int $httpCode = null;
    private ?string $redirectPath = null;
    private array $messages = ['success' => [], 'error' => []];
    private array $logs = [];
    private array $saved = [];
    private array $mails = [];
    private array $filters = [];
    private bool $stockCalled = false;
    private ?FormKeyValidator $formKeyValidator = null;

    /**
     * @param array $options keys: config, post, params, ip, server, spam, counts, mailFailure, saveFailure,
     *                       stockEnabled
     */
    private function controller(array $options = []): Post
    {
        $this->json = [];
        $this->httpCode = null;
        $this->redirectPath = null;
        $this->messages = ['success' => [], 'error' => []];
        $this->logs = [];
        $this->saved = [];
        $this->mails = [];
        $this->filters = [];
        $this->stockCalled = false;

        $configValues = ($options['config'] ?? []) + [
            'isEnabled' => true,
            'isHoneypotEnabled' => false,
            'getMinTime' => 0,
            'isRateLimitEnabled' => false,
            'getMaxPerHour' => 5,
            'isPhoneRequired' => false,
            'isSubjectRequired' => false,
            'getCustomFields' => [],
            'sendConfirmation' => false,
            'getConfirmationMaxPerRecipient' => 2,
            'getConfirmationMaxPerIp' => 5,
            'getSuccessMessage' => 'Thanks!',
        ];
        $config = $this->createStub(Config::class);
        foreach ($configValues as $method => $value) {
            $config->method($method)->willReturn($value);
        }

        $params = $options['params'] ?? [];
        $server = $options['server'] ?? [];
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key, $default = null) => $params[$key] ?? $default
        );
        $request->method('getPostValue')->willReturn(
            array_key_exists('post', $options) ? $options['post'] : self::VALID
        );
        $request->method('getServer')->willReturnCallback(
            static fn($key = null, $default = null) => $server[$key] ?? $default
        );

        $jsonResult = $this->createStub(Json::class);
        $jsonResult->method('setData')->willReturnCallback(function ($data) use (&$jsonResult) {
            $this->json = $data;
            return $jsonResult;
        });
        $jsonResult->method('setHttpResponseCode')->willReturnCallback(function ($code) use (&$jsonResult) {
            $this->httpCode = $code;
            return $jsonResult;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($jsonResult);

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

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(4);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $mail = $this->createStub(Mail::class);
        $mailFailure = $options['mailFailure'] ?? null;
        $mail->method('sendAdminNotification')->willReturnCallback(function ($data) use ($mailFailure) {
            $this->mails['admin'] = $data;
            if ($mailFailure) {
                throw new \RuntimeException('admin mail broke');
            }
        });
        $mail->method('sendCustomerConfirmation')->willReturnCallback(function ($data) use ($mailFailure) {
            $this->mails['customer'] = $data;
            if ($mailFailure) {
                throw new \RuntimeException('customer mail broke');
            }
        });

        $submission = $this->createStub(Submission::class);
        $submission->method('setData')->willReturnCallback(function ($data) use (&$submission) {
            $this->saved['data'] = $data;
            return $submission;
        });
        $submissionFactory = $this->createStub(SubmissionFactory::class);
        $submissionFactory->method('create')->willReturn($submission);

        $resource = $this->createStub(SubmissionResource::class);
        $saveFailure = $options['saveFailure'] ?? null;
        $resource->method('save')->willReturnCallback(function () use ($saveFailure, &$resource) {
            if ($saveFailure) {
                throw $saveFailure;
            }
            $this->saved['saved'] = true;
            return $resource;
        });

        $counts = $options['counts'] ?? [];
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(function () use ($counts) {
            $field = null;
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function ($name, $condition) use (&$field, &$collection) {
                    if ($name !== 'created_at') {
                        $field = $name;
                        $this->filters[] = [$name, $condition];
                    }
                    return $collection;
                }
            );
            $collection->method('getSize')->willReturnCallback(static function () use (&$field, $counts) {
                return $counts[$field] ?? 0;
            });
            return $collection;
        });

        $logger = $this->createStub(LoggerInterface::class);
        foreach (['info', 'error', 'critical'] as $level) {
            $logger->method($level)->willReturnCallback(function ($message, $context = []) use ($level) {
                $this->logs[] = [$level, (string) $message, $context];
            });
        }

        $guard = $this->createStub(ContentGuard::class);
        $spam = $options['spam'] ?? null;
        $guard->method('detect')->willReturnCallback(function ($values, $short) use ($spam) {
            $this->saved['inspected'] = $values;
            $this->saved['short'] = $short;
            return $spam;
        });
        $guard->method('sample')->willReturn('sample-text');

        $this->formKeyValidator = $this->createStub(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn($options['formKeyValid'] ?? true);

        $stockPost = $this->createStub(StockPost::class);
        $stockPost->method('execute')->willReturnCallback(function () use ($redirect) {
            $this->stockCalled = true;
            return $redirect;
        });
        $stockFactory = $this->createStub(StockPostFactory::class);
        $stockFactory->method('create')->willReturn($stockPost);

        $stockConfig = $this->createStub(StockContactConfig::class);
        $stockConfig->method('isEnabled')->willReturn($options['stockEnabled'] ?? false);

        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn($options['ip'] ?? '203.0.113.9');

        return new Post(
            $request,
            $redirectFactory,
            $jsonFactory,
            $messageManager,
            $storeManager,
            $config,
            $mail,
            $submissionFactory,
            $resource,
            $collectionFactory,
            $logger,
            $this->createStub(ResponseInterface::class),
            $guard,
            $this->formKeyValidator,
            $stockFactory,
            $stockConfig,
            $remote
        );
    }

    private function logMessages(string $level): array
    {
        return array_values(array_map(
            static fn($entry) => $entry[1],
            array_filter($this->logs, static fn($entry) => $entry[0] === $level)
        ));
    }

    public function testDisabledModuleDelegatesToTheStockController(): void
    {
        $this->controller(['config' => ['isEnabled' => false], 'stockEnabled' => true])->execute();

        $this->assertTrue($this->stockCalled);
        $this->assertArrayNotHasKey('saved', $this->saved);
    }

    public function testDisabledModuleAndStockContactGives404(): void
    {
        $controller = $this->controller(['config' => ['isEnabled' => false], 'stockEnabled' => false]);

        $this->expectException(NotFoundException::class);
        $controller->execute();
    }

    public function testValidSubmissionIsSavedTrimmedAndAdminNotified(): void
    {
        $this->controller([
            'server' => ['HTTP_USER_AGENT' => str_repeat('a', 600)],
        ])->execute();

        $data = $this->saved['data'];
        $this->assertTrue($this->saved['saved']);
        $this->assertSame('Ann Lee', $data['name']);
        $this->assertSame('ann@shop.test', $data['email']);
        $this->assertSame('Where is my order?', $data['message']);
        $this->assertNull($data['telephone']);
        $this->assertNull($data['subject']);
        $this->assertNull($data['custom_fields']);
        $this->assertSame(0, $data['status']);
        $this->assertSame('203.0.113.9', $data['ip_address']);
        $this->assertSame(500, strlen($data['user_agent']));
        $this->assertSame(4, $data['store_id']);

        $this->assertSame('Contact Form Submission', $this->mails['admin']['subject']);
        $this->assertSame('', $this->mails['admin']['telephone']);
        $this->assertArrayNotHasKey('customer', $this->mails);
        $this->assertSame(['Thanks!'], $this->messages['success']);
        $this->assertSame('contact', $this->redirectPath);
    }

    public function testAjaxSuccessReturnsJson(): void
    {
        $this->controller(['params' => ['ajax' => '1']])->execute();

        $this->assertSame(['success' => true, 'message' => 'Thanks!'], $this->json);
        $this->assertSame([], $this->messages['success']);
        $this->assertNull($this->redirectPath);
    }

    public function testNonArrayFieldsAreRejected(): void
    {
        $this->controller([
            'params' => ['ajax' => '1'],
            'post' => ['name' => ['x'], 'email' => 'a@b.test', 'message' => 'hi'],
        ])->execute();

        $this->assertSame(['success' => false, 'message' => 'Invalid form data.'], $this->json);
        $this->assertArrayNotHasKey('data', $this->saved);
    }

    public function testNonArrayPostIsTreatedAsEmptyAndFailsValidation(): void
    {
        $this->controller(['post' => null])->execute();

        $this->assertSame(['Name is required.'], $this->messages['error']);
        $this->assertSame('contact', $this->redirectPath);
    }

    public function testFilledHoneypotSilentlyPretendsSuccess(): void
    {
        $this->controller([
            'config' => ['isHoneypotEnabled' => true],
            'post' => self::VALID + [ContactForm::HONEYPOT_FIELD => 'http://bot.test'],
        ])->execute();

        $this->assertSame(['Thanks!'], $this->messages['success']);
        $this->assertArrayNotHasKey('data', $this->saved);
        $this->assertArrayNotHasKey('admin', $this->mails);
    }

    public function testHoneypotIsIgnoredWhenDisabled(): void
    {
        $this->controller([
            'post' => self::VALID + [ContactForm::HONEYPOT_FIELD => 'filled'],
        ])->execute();

        $this->assertTrue($this->saved['saved']);
    }

    public function testContentGuardHitIsLoggedAndSilentlyDropped(): void
    {
        $this->controller(['spam' => 'blocked domain: x'])->execute();

        $this->assertArrayNotHasKey('saved', $this->saved);
        $this->assertSame(['Thanks!'], $this->messages['success']);
        $info = array_values(array_filter($this->logs, static fn($l) => $l[0] === 'info'));
        $this->assertStringContainsString('blocked by the content guard', $info[0][1]);
        $this->assertSame('blocked domain: x', $info[0][2]['reason']);
        $this->assertSame('203.0.113.9', $info[0][2]['ip']);
        $this->assertSame('sample-text', $info[0][2]['sample']);
    }

    public function testOnlyFreeTextFieldsAreInspectedByTheGuard(): void
    {
        $this->controller([
            'post' => self::VALID + [
                'subject' => 'Hello',
                'telephone' => '123',
                'form_key' => 'abc',
                'ajax' => '1',
                '_private' => 'x',
                'custom_company' => 'Acme',
                'unrelated' => 'zzz',
                ContactForm::TIMESTAMP_FIELD => '1',
            ],
        ])->execute();

        $this->assertSame(['name', 'message', 'subject', 'custom_company'], array_keys($this->saved['inspected']));
        $this->assertSame(['name', 'subject'], $this->saved['short']);
    }

    public function testTooFastSubmissionIsDropped(): void
    {
        $this->controller([
            'config' => ['getMinTime' => 5],
            'post' => self::VALID + [ContactForm::TIMESTAMP_FIELD => (string) time()],
        ])->execute();

        $this->assertArrayNotHasKey('saved', $this->saved);
        $this->assertSame(['Thanks!'], $this->messages['success']);
    }

    public function testSlowEnoughSubmissionIsAccepted(): void
    {
        $this->controller([
            'config' => ['getMinTime' => 5],
            'post' => self::VALID + [ContactForm::TIMESTAMP_FIELD => (string) (time() - 60)],
        ])->execute();

        $this->assertTrue($this->saved['saved']);
    }

    public function testMissingAntiSpamFieldsAreLoggedButStillProcessed(): void
    {
        $this->controller(['config' => ['isHoneypotEnabled' => true, 'getMinTime' => 3]])->execute();

        $this->assertTrue($this->saved['saved']);
        $this->assertStringContainsString('anti-spam fields were not present', $this->logMessages('info')[0]);
    }

    public function testNoAntiSpamWarningWhenAProtectionFieldIsPresent(): void
    {
        $this->controller([
            'config' => ['isHoneypotEnabled' => true],
            'post' => self::VALID + [ContactForm::HONEYPOT_FIELD => ''],
        ])->execute();

        $this->assertSame([], $this->logMessages('info'));
    }

    public function testNoAntiSpamWarningWhenBothProtectionsAreOff(): void
    {
        $this->controller()->execute();

        $this->assertSame([], $this->logMessages('info'));
    }

    public function testRateLimitBlocksAtTheHourlyMaximum(): void
    {
        $this->controller([
            'params' => ['ajax' => 1],
            'config' => ['isRateLimitEnabled' => true, 'getMaxPerHour' => 3],
            'counts' => ['ip_address' => 3],
        ])->execute();

        $this->assertSame(
            ['success' => false, 'message' => 'Too many submissions. Please try again later.'],
            $this->json
        );
        $this->assertSame([['ip_address', '203.0.113.9']], $this->filters);
        $this->assertArrayNotHasKey('saved', $this->saved);
    }

    public function testRateLimitAllowsBelowTheMaximum(): void
    {
        $this->controller([
            'config' => ['isRateLimitEnabled' => true, 'getMaxPerHour' => 3],
            'counts' => ['ip_address' => 2],
        ])->execute();

        $this->assertTrue($this->saved['saved']);
    }

    public function testEmptyRemoteAddressFallsBackToServerVariableAndIsTruncated(): void
    {
        $this->controller([
            'ip' => '',
            'server' => ['REMOTE_ADDR' => str_repeat('9', 60)],
        ])->execute();

        $this->assertSame(str_repeat('9', 45), $this->saved['data']['ip_address']);
    }

    public static function invalidProvider(): array
    {
        return [
            'no name' => [['name' => '  '] + self::VALID, [], 'Name is required.'],
            'bad email' => [['email' => 'not-an-email'] + self::VALID, [], 'A valid email address is required.'],
            'no message' => [['message' => ''] + self::VALID, [], 'Message is required.'],
            'phone required' => [self::VALID, ['isPhoneRequired' => true], 'Phone number is required.'],
            'subject required' => [
                self::VALID + ['subject' => ' '],
                ['isSubjectRequired' => true],
                'Subject is required.',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProvider')]
    public function testValidationErrorsAreShownAndNothingIsSaved(array $post, array $config, string $error): void
    {
        $this->controller(['post' => $post, 'config' => $config])->execute();

        $this->assertSame([$error], $this->messages['error']);
        $this->assertArrayNotHasKey('data', $this->saved);
        $this->assertSame('contact', $this->redirectPath);
    }

    public function testRequiredPhoneAndSubjectAreStoredWhenGiven(): void
    {
        $this->controller([
            'post' => self::VALID + ['telephone' => ' 555 ', 'subject' => ' Help '],
            'config' => ['isPhoneRequired' => true, 'isSubjectRequired' => true],
        ])->execute();

        $this->assertSame('555', $this->saved['data']['telephone']);
        $this->assertSame('Help', $this->saved['data']['subject']);
        $this->assertSame('Help', $this->mails['admin']['subject']);
    }

    public function testCustomFieldsAreNormalisedStoredAndRenderedEscaped(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [
                ['label' => 'Company Name', 'type' => 'text'],
                ['label' => 'Topics', 'type' => 'checkbox'],
                ['label' => 'Unsent', 'type' => 'text'],
            ]],
            'post' => self::VALID + [
                'custom_company_name' => ' <b>Acme</b> ',
                'custom_topics' => [' a ', ['nested'], 'b'],
            ],
        ])->execute();

        $stored = json_decode($this->saved['data']['custom_fields'], true);
        $this->assertSame(['Company Name' => '<b>Acme</b>', 'Topics' => ['a', 'b']], $stored);
        $html = $this->mails['admin']['custom_fields_html'];
        $this->assertStringContainsString('&lt;b&gt;Acme&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Acme', $html);
        $this->assertStringContainsString('a, b', $html);
        $this->assertStringNotContainsString('Unsent', $html);
    }

    public function testCustomFieldsUseTheirConfiguredUniqueKeys(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [
                ['label' => 'Phone #', 'type' => 'text', 'key' => 'custom_phone__'],
                ['label' => 'Phone ?', 'type' => 'text', 'key' => 'custom_phone___2'],
            ]],
            'post' => self::VALID + [
                'custom_phone__' => 'home',
                'custom_phone___2' => 'work',
            ],
        ])->execute();

        $stored = json_decode($this->saved['data']['custom_fields'], true);
        $this->assertSame(['Phone #' => 'home', 'Phone ?' => 'work'], $stored);
    }

    public function testRequiredCustomFieldMustBeFilled(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [['label' => 'Order', 'type' => 'text', 'required' => '1']]],
            'post' => self::VALID + ['custom_order' => '   '],
        ])->execute();

        $this->assertSame(['Order is required.'], $this->messages['error']);
        $this->assertArrayNotHasKey('data', $this->saved);
    }

    public function testRequiredCheckboxWithOnlyEmptyValuesIsRejected(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [['label' => 'Topics', 'type' => 'checkbox', 'required' => 1]]],
            'post' => self::VALID + ['custom_topics' => ['', ' ']],
        ])->execute();

        $this->assertSame(['Topics is required.'], $this->messages['error']);
    }

    public function testSelectValueMustBeOneOfTheOptions(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [['label' => 'Budget', 'type' => 'select', 'options' => 'Low, High']]],
            'post' => self::VALID + ['custom_budget' => 'Huge'],
        ])->execute();

        $this->assertSame(['Please select a valid option for Budget.'], $this->messages['error']);
    }

    public function testSelectValueFromTheOptionsIsAccepted(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [['label' => 'Budget', 'type' => 'radio', 'options' => 'Low, High']]],
            'post' => self::VALID + ['custom_budget' => 'High'],
        ])->execute();

        $this->assertSame('{"Budget":"High"}', $this->saved['data']['custom_fields']);
    }

    public function testOptionsAreNotEnforcedForFreeTextTypes(): void
    {
        $this->controller([
            'config' => ['getCustomFields' => [['label' => 'Note', 'type' => 'text', 'options' => 'A,B']]],
            'post' => self::VALID + ['custom_note' => 'anything'],
        ])->execute();

        $this->assertTrue($this->saved['saved']);
    }

    public function testConfirmationIsSentWithinTheEmailRateLimit(): void
    {
        $this->controller([
            'config' => ['sendConfirmation' => true],
            'counts' => ['email' => 2, 'ip_address' => 5],
        ])->execute();

        $this->assertSame('ann@shop.test', $this->mails['customer']['email']);
        $this->assertSame([['email', 'ann@shop.test'], ['ip_address', '203.0.113.9']], $this->filters);
    }

    public function testConfirmationIsSkippedWhenTheRecipientLimitIsExceeded(): void
    {
        $this->controller([
            'config' => ['sendConfirmation' => true],
            'counts' => ['email' => 3],
        ])->execute();

        $this->assertArrayNotHasKey('customer', $this->mails);
        $this->assertStringContainsString('confirmation skipped', $this->logMessages('info')[0]);
        $this->assertSame(['Thanks!'], $this->messages['success']);
    }

    public function testConfirmationIsSkippedWhenTheIpLimitIsExceeded(): void
    {
        $this->controller([
            'config' => ['sendConfirmation' => true],
            'counts' => ['ip_address' => 6],
        ])->execute();

        $this->assertArrayNotHasKey('customer', $this->mails);
    }

    public function testMailFailuresAreLoggedAndStillReportSuccess(): void
    {
        $this->controller([
            'config' => ['sendConfirmation' => true],
            'mailFailure' => true,
        ])->execute();

        $this->assertSame(
            ['Panth Contact admin email: admin mail broke', 'Panth Contact customer email: customer mail broke'],
            $this->logMessages('error')
        );
        $this->assertSame(['Thanks!'], $this->messages['success']);
    }

    public function testUnexpectedErrorsAreLoggedAndAGenericMessageShown(): void
    {
        $this->controller([
            'params' => ['ajax' => '1'],
            'saveFailure' => new \RuntimeException('db gone'),
        ])->execute();

        $this->assertSame(
            ['success' => false, 'message' => 'An error occurred. Please try again later.'],
            $this->json
        );
        $this->assertSame(['Panth Contact form error: db gone'], $this->logMessages('critical'));
    }

    public function testCsrfValidationDelegatesToTheFormKeyValidator(): void
    {
        $request = $this->createStub(Http::class);

        $this->assertTrue($this->controller(['formKeyValid' => true])->validateForCsrf($request));
        $this->assertFalse($this->controller(['formKeyValid' => false])->validateForCsrf($request));
    }

    public function testCsrfExceptionIsNullForNormalPosts(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn(null);

        $this->assertNull($this->controller()->createCsrfValidationException($request));
    }

    public function testCsrfExceptionForAjaxIsA403Json(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(static fn($key) => $key === 'ajax' ? '1' : null);

        $exception = $this->controller()->createCsrfValidationException($request);

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertSame(403, $this->httpCode);
        $this->assertSame(
            ['success' => false, 'message' => 'Invalid Form Key. Please refresh the page.'],
            $this->json
        );
    }

    public function testExposesRequestAndResponse(): void
    {
        $controller = $this->controller();

        $this->assertInstanceOf(Http::class, $controller->getRequest());
        $this->assertInstanceOf(ResponseInterface::class, $controller->getResponse());
    }
}
