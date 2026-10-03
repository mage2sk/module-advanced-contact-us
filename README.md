# Magento 2 Advanced Contact Us

Panth Advanced Contact Us replaces the stock Magento 2 contact page at `/contact` with its own form, controller and layout. The form is submitted with plain JavaScript over AJAX, every accepted submission is written to a database table, and the store owner gets an email notification with an optional confirmation email to the sender. Merchants can add extra form fields from the admin configuration, show a contact-details sidebar, and screen submissions with a honeypot field, a content check, a minimum fill time and a per-IP rate limit. Submissions are managed in an admin grid under "Panth Extensions".

The module takes over the `contact` frontend route ahead of `Magento_Contact`. While "Enable Module" is Yes it removes the default contact form blocks from the contact page and renders its own block instead. It is written for Luma and Luma-based themes; the template uses inline CSS and vanilla JavaScript (no Alpine.js, Tailwind or jQuery) and its layout update also removes the Hyva contact page blocks, so it renders on Hyva storefronts as a self-styled page.

Product page: [kishansavaliya.com/magento-2-advanced-contact-us.html](https://kishansavaliya.com/magento-2-advanced-contact-us.html)

## Features

- Replaces the `/contact` page and the `/contact/index/post` action with the module's own controllers.
- AJAX submission with client-side checks for the required fields and the email format; if JavaScript is unavailable the form falls back to a normal POST and a redirect back to `/contact` with a success or error message.
- Name, email and message fields are always present; phone and subject fields can be shown or hidden and made required independently.
- Custom fields defined in configuration with types text, textarea, select, radio, checkbox, email and tel, each with label, placeholder, required flag and comma-separated options (select and radio). The required flag and the option list are checked in the browser and on the server. Values are stored as JSON with the submission.
- Name and email are pre-filled for logged-in customers.
- Optional sidebar showing the configured email address, phone number, address and business hours.
- Configurable page title and success message.
- Spam screening, in this order: honeypot field, content guard (blocked domains, money-transfer phrasing, three or more links, URL in the name or subject), minimum fill time, then per-IP rate limit over the last hour. The first three fail silently with the normal success response; the rate limit returns the error "Too many submissions. Please try again later."
- Blocked content-guard submissions are written to the Magento log at info level with the reason, the IP address and a 200-character sample.
- The client IP is read through Magento `RemoteAddress`, so alternative client IP headers configured for a trusted proxy or CDN are honoured. A spoofed `X-Forwarded-For` header is ignored unless such a header has been configured.
- Name, email, phone, subject and message must be plain text values. A request that sends one of them as an array gets the error "Invalid form data." instead of a server error.
- Every accepted submission is stored in `panth_contact_submission` with IP address, user agent and store ID.
- Admin notification email with Reply-To set to the sender, and an optional confirmation email to the sender. Both are registered email templates that can be customised under Marketing > Email Templates.
- The confirmation email is a fixed acknowledgement. It does not repeat the submitted name, subject, message or custom fields, so the form cannot be used to send arbitrary text to a third-party address. It is limited per recipient address and per client IP within one hour.
- Admin grid with keyword search (name, email, subject, phone, message), filters (including a Store filter that resets to All Store Views), sorting, column controls, bookmarks and a mass Delete action; a detail page shows the submission, its custom fields, IP address, store and date, with "Reply via Email" (a mailto link) and Delete buttons.
- Status values New, Read and Replied; a submission changes from New to Read when opened in the admin.
- A block wrapper injects the hidden anti-spam fields into a theme-overridden template that omits them, and the controller logs an info message when they are missing from a submission.
- Unit tests for the content guard and the anti-spam view model under `Test/Unit`.

## Compatibility

| Platform | Versions |
|---|---|
| Magento Open Source | 2.4.4 to 2.4.8 (as published on the product page) |
| Adobe Commerce | 2.4.4 to 2.4.8 (as published on the product page) |
| PHP | 8.1 or later (`composer.json` requires `php >=8.1`) |
| Themes | Luma and Luma-based themes; renders on Hyva with its own inline styling |

Composer constraints on Magento packages: `magento/framework >=103.0`, `magento/module-contact >=100.4`, `magento/module-backend >=102.0`, `magento/module-ui >=101.2`.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8
- PHP 8.1 or later
- `mage2kishan/module-core` `^1.0` (Panth_Core; installed automatically by Composer). The admin menu entry is placed under the "Panth Extensions" menu that Panth_Core provides.
- `Magento_Contact`, `Magento_Backend` and `Magento_Ui` must be enabled (the module is sequenced after them).

No other packages are required or suggested in `composer.json`.

## Installation

```bash
composer require mage2kishan/module-advanced-contact-us
bin/magento module:enable Panth_Core Panth_AdvancedContactUs
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

`setup:di:compile` is only needed when the store runs in production mode. The module ships no files under `view/*/web`, so no static content deployment is required.

Check the result with:

```bash
bin/magento module:status Panth_AdvancedContactUs
```

After installation open `/contact` on the storefront to see the new form, and in the admin open Panth Extensions > Contact Us > Submissions.

## Configuration

Admin path: Stores > Configuration > Panth Extensions > "Advanced Contact Us". All fields can be set at default, website and store view scope. The ACL resource for this section is `Panth_AdvancedContactUs::config`.

### "General Settings"

| Setting | Default | What it does |
|---|---|---|
| "Enable Module" | Yes | When set to No the contact page shows the stock `Magento_Contact` form and posts are handed to the stock `Magento_Contact` controller; nothing is stored in `panth_contact_submission`. |
| "Page Title" | Contact Us | Heading of the contact page and the browser title. |
| "Success Message" | Thank you for reaching out! We've received your message and will get back to you within 24 hours. | Text shown after a successful submission (and after silently dropped spam). |
| "Show Contact Info Sidebar" | Yes | Shows the "Get in Touch" card next to the form. |

### "Contact Info (Sidebar)"

| Setting | Default | What it does |
|---|---|---|
| "Email Address" | hello@example.com | Shown as a mailto link in the sidebar. Falls back to hello@example.com when empty. |
| "Phone Number" | 1-800-SHOP-NOW | Shown as a tel link in the sidebar; hidden when empty. |
| "Address" | Your Store Address | Shown in the sidebar; hidden when empty. |
| "Business Hours" | Mon - Fri: 9:00 AM - 6:00 PM | Shown in the sidebar; hidden when empty. |

### "Form Fields"

| Setting | Default | What it does |
|---|---|---|
| "Show Phone Field" | Yes | Adds the phone input to the form. |
| "Phone Required" | No | Makes the phone field mandatory (checked in the browser and on the server). |
| "Show Subject Field" | Yes | Adds the subject input to the form. |
| "Subject Required" | No | Makes the subject field mandatory. |
| "Custom Fields" | (none) | Dynamic rows with columns "Field Label", "Type", "Required", "Placeholder" and "Options". Type is one of text, textarea, select, radio, checkbox, email, tel; Required is 1 or 0; Options is a comma-separated list for select and radio. Rows without a label are ignored. A required custom field left empty is refused with "<Label> is required.", and a select or radio value that is not one of the options is refused with "Please select a valid option for <Label>." |

### "Email Settings"

| Setting | Default | What it does |
|---|---|---|
| "Admin Notification Email" | hello@example.com | Recipient of the notification sent for every accepted submission. |
| "Sender Email Identity" | General Contact | Store email identity used as the From address of both emails. |
| "Send Customer Confirmation" | Yes | Sends a short acknowledgement to the address entered in the form. The submitted text is not included. Set to No to send no copy. |
| "Max Confirmations Per Recipient Per Hour" | 2 | Confirmation emails sent to one address within an hour. Later submissions are still stored and notified to the admin, but no confirmation is sent. 0 sends none. |
| "Max Confirmations Per Client IP Per Hour" | 5 | Confirmation emails triggered from one client IP within an hour. 0 sends none. |

The template identifiers are stored in `panth_advancedcontact/email/admin_template` (default `panth_advancedcontact_admin_notification`) and `panth_advancedcontact/email/customer_template` (default `panth_advancedcontact_customer_confirmation`). They are set in `etc/config.xml` only and have no field in the admin form.

### "Bot Protection"

| Setting | Default | What it does |
|---|---|---|
| "Enable Honeypot" | Yes | Adds a hidden `website_url` input. A submission with a value in it receives the success response and is discarded. |
| "Block Spam By Message Content" | Yes | Runs the content guard over the name, subject, message and custom field values. Matches are discarded with the success response, not stored and not emailed. |
| "Additional Blocked Terms" | (empty) | Extra terms or domains, one per line (commas also separate terms), added to the built-in list of link-shortener domains. Matched whole-word and case-insensitively. Shown only when the content guard is enabled. |
| "Enable Rate Limiting" | Yes | Counts stored submissions from the same client IP address in the last hour and rejects further ones with an error message. |
| "Max Submissions Per Hour (per IP)" | 5 | Limit used by rate limiting. Shown only when rate limiting is enabled. An empty or 0 value falls back to 5. |
| "Minimum Form Fill Time (seconds)" | 2 | A hidden timestamp is rendered with the form; a submission arriving sooner than this many seconds after rendering receives the success response and is discarded. 0 turns the check off; an empty value falls back to 2. |

Configuration paths:

- `panth_advancedcontact/general/enabled`, `page_title`, `success_message`, `show_info`
- `panth_advancedcontact/contact_info/email`, `phone`, `address`, `hours`
- `panth_advancedcontact/fields/show_phone`, `phone_required`, `show_subject`, `subject_required`, `custom_fields`
- `panth_advancedcontact/email/recipient_email`, `sender_email_identity`, `send_confirmation`, `confirmation_max_per_recipient`, `confirmation_max_per_ip`, `admin_template`, `customer_template`
- `panth_advancedcontact/protection/honeypot`, `content_guard`, `blocked_terms`, `rate_limit`, `max_per_hour`, `min_time`

With the defaults the module is active immediately after installation: the form shows name, email, phone, subject and message, the sidebar shows the placeholder contact details, all four protection layers are on, and both emails go to hello@example.com until "Admin Notification Email" and "Email Address" are changed.

## Usage

### Contact form

The form at `/contact` posts to `contact/index/post`, built with the store URL so store codes in URLs and installs under a sub-path work. It contains:

- Name (required), Email (required, must be a valid email address), Message (required)
- Phone (`telephone`) and Subject when enabled, each optionally required
- One input per configured custom field, named `custom_` followed by the lower-cased label with any character other than a-z, 0-9 and underscore replaced by an underscore; when two labels produce the same name, the later fields get `_2`, `_3` and so on appended
- Hidden `form_key`, honeypot `website_url` and `_timestamp` inputs

On success the form card is replaced by the "Message Sent!" panel with the configured success message. Validation errors from the server are shown above the form.

### Where submissions go

- Database: every accepted submission is a row in `panth_contact_submission` with status 0 (New).
- Admin notification: sent to "Admin Notification Email" using the template "Panth Contact - Admin Notification" with Reply-To set to the sender's name and address. The subject is "New Contact Form Submission: " followed by the subject field (or "Contact Form Submission" when the subject is empty). Custom fields are rendered as extra table rows.
- Sender confirmation: when "Send Customer Confirmation" is Yes and the per-recipient and per-IP limits allow it, the template "Panth Contact - Customer Confirmation" is sent to the address entered, with the subject "Thank you for contacting " followed by the store name. Only the store is passed to the template; the `data` variable is empty.
- Email failures are logged and do not stop the submission from being stored or the success response from being returned.

### Admin pages

- Panth Extensions > Contact Us > Submissions: grid with columns ID, Name, Email, Subject, Phone (hidden by default), Status, IP Address (hidden), Store (hidden), Submitted and Actions (View, Delete). Mass action: Delete.
- Panth Extensions > Contact Us > Configuration: shortcut to the configuration section.
- Submission detail page (`panthcontact/submission/view`): name, email, phone, subject, message, custom fields, IP address, submitted date and store ID, with "Back to List", "Reply via Email" and "Delete" buttons. Opening a New submission marks it Read.
- Delete, from the grid row action and from the detail page, is sent as a POST request with the form key. A GET request to the delete URL is refused.

There is no attachment or file upload support.

### Spam protection as coded

1. Honeypot: if `website_url` is not empty the request gets the success response and nothing is saved.
2. Content guard (`Model\Spam\ContentGuard`): the name, subject, message and `custom_*` values are checked for built-in shortener domains (share.google, t.me, bit.ly, tinyurl.com, goo.gl, is.gd, cutt.ly, rebrand.ly, rb.gy, shorturl.at) plus the "Additional Blocked Terms"; for Russian-language money-transfer phrases combined with a currency mention or a link; for three or more `http(s)://` links; and for a URL or `www.` in the name or subject when that value is 40 characters or shorter. A match gets the success response, is logged, and is not saved or emailed.
3. Minimum fill time: if the hidden timestamp is present and fewer than "Minimum Form Fill Time (seconds)" have elapsed, the request gets the success response and nothing is saved.
4. Rate limit: stored submissions from the same client IP address in the last hour (measured with the database clock) are counted; at or above "Max Submissions Per Hour (per IP)" the request fails with an error message.

Email and telephone values are not inspected by the content guard. Blocked submissions are not counted towards the rate limit because they are never stored.

### Templates that can be overridden

- `view/frontend/templates/form.phtml`: the contact page (form, sidebar, inline CSS and JavaScript). Copy it to `app/design/frontend/<Vendor>/<theme>/Panth_AdvancedContactUs/templates/form.phtml`. Keep the call `$viewModel->getAntiSpamFieldsHtml()` inside the `<form>`; if it is missing, `Block\Frontend\Form` inserts the fields after the opening `<form>` tag, and when they still do not arrive the controller logs a notice and skips the honeypot and timing checks.
- `view/adminhtml/templates/submission/view.phtml`: the admin detail page.
- `view/frontend/email/admin_notification.html` and `view/frontend/email/customer_confirmation.html`: the email templates, also editable in the admin under Marketing > Email Templates.
- The frontend CSS reads custom properties `--contact-primary`, `--contact-primary-hover`, `--contact-bg`, `--contact-card-bg`, `--contact-border`, `--contact-text`, `--contact-text-muted`, `--contact-input-bg`, `--contact-input-focus`, `--contact-radius`, `--contact-success` and `--contact-error`, with fallbacks in the template; `etc/theme-config.json` declares their defaults and `etc/frontend/di.xml` registers the module with `Panth\Core\ViewModel\ThemeConfig`. The Send Message button uses the theme fill shade `--color-primary-fill` (default #0F766E) so its white label meets WCAG AA contrast; `--contact-primary` drives the checkbox and icon accents.

## Developer Notes

- Module name: `Panth_AdvancedContactUs`; Composer package: `mage2kishan/module-advanced-contact-us`; namespace: `Panth\AdvancedContactUs`; version 1.1.2.
- Sequence: `Magento_Contact`, `Magento_Backend`, `Magento_Ui`, `Panth_Core`.
- Routes: frontend `contact` (registered before `Magento_Contact`) served by `Controller\Index\Index` (GET) and `Controller\Index\Post` (POST); admin `panthcontact` with `Controller\Adminhtml\Submission\Index`, `View`, `Delete` and `MassDelete`.
- Layout: `Controller\Index\Index` adds the handle `panth_advancedcontactus_form` when "Enable Module" is Yes. That handle removes `contactForm`, `contact.details`, `form.additional.info`, `form.additional.after` and `page.main.title`, and adds the non-cacheable block `panth.contact.form` (`Block\Frontend\Form`) with view model `ViewModel\ContactForm`. `contact_index_index.xml` only sets the `1column` page layout.
- `Model\Config`: typed getters for every configuration value.
- `ViewModel\ContactForm`: form key, form action, logged-in customer name and email, custom fields as JSON, `getAntiSpamFieldsHtml()`; constants `HONEYPOT_FIELD` (`website_url`), `TIMESTAMP_FIELD` (`_timestamp`) and `MARKER` (`data-panth-antispam`).
- `Model\Spam\ContentGuard`: `detect(array $values, array $shortFieldKeys): ?string` returns the block reason or null; `sample()` returns a short text sample for logging.
- `Model\Mail`: `sendAdminNotification(array $data)` and `sendCustomerConfirmation(array $data)` built on `Magento\Framework\Mail\Template\TransportBuilder`.
- `Model\Submission` (constants `STATUS_NEW` = 0, `STATUS_READ` = 1, `STATUS_REPLIED` = 2), `Model\ResourceModel\Submission` and `Model\ResourceModel\Submission\Collection`.
- `Controller\Index\Post` implements `CsrfAwareActionInterface` and validates the `form_key` in `validateForCsrf()`. An invalid form key gets a JSON error (HTTP 403) for AJAX requests and the standard Magento redirect with "Invalid Form Key" otherwise. Responses are JSON when the `ajax` parameter is set, otherwise a redirect to `/contact` with a session message. When "Enable Module" is No the request is passed to `Magento\Contact\Controller\Index\Post`.
- `etc/di.xml` registers the virtual type `Panth\AdvancedContactUs\Model\ResourceModel\Submission\Grid\Collection` as data source `panth_contact_submission_listing_data_source` for the UI listing `panth_contact_submission_listing`. Grid columns `Status` and `Actions` are `Ui\Component\Listing\Column\Status` and `Ui\Component\Listing\Column\Actions`.
- No plugins, preferences, observers, console commands or cron jobs are declared.
- Email templates (`etc/email_templates.xml`): `panth_advancedcontact_admin_notification` and `panth_advancedcontact_customer_confirmation`, area frontend.
- ACL resources (`etc/acl.xml`): `Panth_AdvancedContactUs::config` ("Panth Advanced Contact Us", under Stores > Configuration), `Panth_AdvancedContactUs::contact` ("Contact Submissions"), `Panth_AdvancedContactUs::submission_view` ("View Submissions"), `Panth_AdvancedContactUs::submission_delete` ("Delete Submissions").
- Database table (`etc/db_schema.xml`): `panth_contact_submission` with columns `submission_id`, `name`, `email`, `telephone`, `subject`, `message`, `custom_fields` (JSON), `status`, `ip_address`, `user_agent`, `store_id`, `created_at`; indexes on `email`, `status`, `store_id` and `created_at`.
- Admin strings in `view/adminhtml/templates/submission/view.phtml` are not wrapped in `__()`, and the module ships no `i18n` directory.

## Uninstallation

```bash
bin/magento module:disable Panth_AdvancedContactUs
composer remove mage2kishan/module-advanced-contact-us
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The module has no uninstall script. The `panth_contact_submission` table and its rows, and the `panth_advancedcontact/*` values in `core_config_data`, remain in the database until removed manually. Disabling the module restores the stock `Magento_Contact` page.

## Support

- Product page: [kishansavaliya.com/magento-2-advanced-contact-us.html](https://kishansavaliya.com/magento-2-advanced-contact-us.html)
- Contact form: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- Issues: [GitHub issues](https://github.com/mage2sk/module-advanced-contact-us/issues)

## Documentation

[USER_GUIDE.md](USER_GUIDE.md) walks through the configuration groups, the admin submission grid and statuses, creating custom fields, and troubleshooting (form not showing, emails not sending, bot protection too strict, custom fields not appearing).

## License

Commercial software license. See [LICENSE.txt](LICENSE.txt) in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions: [kishansavaliya.com/magento-extensions.html](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [mage2sk/module-advanced-contact-us](https://github.com/mage2sk/module-advanced-contact-us)
- Packagist: [mage2kishan/module-advanced-contact-us](https://packagist.org/packages/mage2kishan/module-advanced-contact-us)
