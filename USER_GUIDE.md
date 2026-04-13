# Panth Advanced Contact Us - User Guide

## Overview

Panth Advanced Contact Us replaces the default Magento contact form with a modern, conversion-optimized contact page featuring custom fields, bot protection, and full admin submission management.

## Configuration

Navigate to **Stores > Configuration > Panth Extensions > Advanced Contact Us**.

### General Settings

- **Enable Module** — master toggle
- **Page Title** — contact page heading (default: "Contact Us")
- **Success Message** — message shown after successful submission
- **Show Contact Info** — display sidebar with contact details

### Contact Information (Sidebar)

- **Email** — contact email address
- **Phone** — phone number
- **Address** — physical address
- **Business Hours** — operating hours text

### Form Fields

- **Show Phone Field** — display phone number field
- **Phone Required** — make phone field mandatory
- **Show Subject Field** — display subject line field
- **Subject Required** — make subject field mandatory
- **Custom Fields** — add unlimited custom fields with type, label, required status, placeholder, and options (for select/radio/checkbox)

### Email Settings

- **Recipient Email** — admin notification recipient
- **Sender Identity** — email sender (general, sales, support, custom)
- **Send Customer Confirmation** — send confirmation email to customer
- **Admin Email Template** — template for admin notifications
- **Customer Email Template** — template for customer confirmations

### Bot Protection

- **Enable Honeypot** — hidden field trap for bots
- **Enable Rate Limiting** — limit submissions per IP
- **Max Per Hour** — maximum submissions per IP per hour (default: 5)
- **Minimum Fill Time** — minimum seconds to fill form (default: 2)

## Admin Submission Management

Navigate to **Panth Extensions > Contact Submissions** in the admin menu.

### Submission Grid

- View all submissions with name, email, subject, status, and date
- Filter and sort by any column
- Bulk delete submissions

### Submission Status

- **New** (orange) — fresh submission, not yet viewed
- **Read** (blue) — viewed by admin
- **Replied** (green) — admin has responded

Submissions are automatically marked as "Read" when viewed in admin.

## Custom Fields

Create custom fields in admin configuration:
1. Go to Form Fields > Custom Fields
2. Click "Add" to add a new field
3. Set field type: text, textarea, select, radio, checkbox, email, or tel
4. Configure label, required status, placeholder
5. For select/radio/checkbox: add options (comma-separated)
6. Save configuration and flush cache

Custom field values are stored as JSON and displayed in admin submission view and email notifications.

## Troubleshooting

- **Form not showing** — ensure module is enabled and cache is flushed
- **Emails not sending** — verify recipient email and Magento email configuration
- **Bot protection too aggressive** — increase max per hour or decrease minimum fill time
- **Custom fields not appearing** — flush cache after adding new fields
