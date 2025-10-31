# Moodle Paddle Enrolment Plugin

A Moodle enrolment plugin that integrates with Paddle Billing API for course payments.

## Overview

This plugin allows you to sell course access through Paddle (Merchant of Record), handling payments, taxes, and compliance automatically.

## Features

- **Paddle Billing API Integration** - Uses modern Paddle Billing API (not Classic)
- **Secure Webhooks** - HMAC signature verification for webhook authenticity
- **Multiple Currencies** - Supports 25+ currencies via Paddle
- **Instant Enrollment** - Users enrolled immediately after payment confirmation
- **Sandbox/Live Modes** - Test with Paddle Sandbox before going live
- **GDPR Compliant** - Privacy provider implementation included

## Requirements

- Moodle 4.5+ (2024100100)
- PHP 7.4+
- cURL extension
- OpenSSL extension
- Active Paddle account ([Sign up here](https://www.paddle.com))

## Installation

### Method 1: Git Clone (Recommended)

```bash
cd /path/to/moodle/enrol/
git clone https://github.com/imbowen1973/Paddle.git paddle
cd paddle
```

Then visit **Site Administration → Notifications** to complete installation.

### Method 2: Manual Upload

1. Download the latest release as ZIP
2. Extract to `/path/to/moodle/enrol/paddle/`
3. Visit **Site Administration → Notifications**
4. Click **Upgrade Moodle database now**

### Method 3: Moodle Plugin Installer

1. Download ZIP from releases
2. **Site Administration → Plugins → Install plugins**
3. Upload ZIP file
4. Click **Install plugin from ZIP file**

## Configuration

### 1. Get Paddle Credentials

**API Key:**
1. Login to [Paddle Dashboard](https://vendors.paddle.com/)
2. Go to **Developer Tools → Authentication**
3. Create a new API key (give it a name like "Moodle Production")
4. Copy the key (starts with `live_` or `test_`)

**Webhook Secret:**
1. Go to **Developer Tools → Notifications**
2. Click **New Notification Destination**
3. Destination URL: `https://yourmoodle.com/enrol/paddle/ipn.php`
4. Subscribe to events:
   - `transaction.completed`
   - `transaction.paid`
   - `transaction.updated` (optional)
5. Copy the **Secret Key** shown

### 2. Configure in Moodle

Navigate to: **Site Administration → Plugins → Enrolments → Paddle**

**Required Settings:**
- **Environment:**
  - Use `Sandbox` for testing
  - Use `Live` for production
- **Live API URL:** `https://api.paddle.com` (default)
- **Sandbox API URL:** `https://sandbox-api.paddle.com` (default)
- **Paddle API Key:** Paste your API key from step 1
- **Webhook Secret Key:** Paste your webhook secret from step 1
- **Webhook HMAC Algorithm:** `HMAC-SHA256` (recommended)

**Optional Settings:**
- **Integration Identifier:** Optional identifier for Paddle support
- **Mail Students:** Send enrollment confirmation to students
- **Mail Teachers:** Notify teachers of new enrollments
- **Mail Admins:** Notify admins of new enrollments
- **Expired Action:** What to do when enrollment expires

**Instance Defaults:**
- **Default Cost:** Default price for courses (can be overridden per course)
- **Currency:** Default currency (USD, EUR, GBP, etc.)
- **Default Role:** Role to assign (usually Student)
- **Enrollment Duration:** How long enrollment lasts (0 = unlimited)

### 3. Enable the Plugin

1. **Site Administration → Plugins → Enrolments → Manage enrol plugins**
2. Click the **eye icon** next to "Paddle" to enable it
3. Optionally move it up in the priority order

## Usage

### Add Paddle Enrollment to a Course

1. Go to your course
2. **Course Administration → Users → Enrolment methods**
3. Click **Add method → Paddle**
4. Configure:
   - **Custom instance name:** Optional (e.g., "Premium Access")
   - **Enroll cost:** Price (e.g., 29.99)
   - **Currency:** Select from available currencies
   - **Assign role:** Usually "Student"
   - **Enrollment period:** Duration or 0 for unlimited
   - **Start/End dates:** Optional enrollment window
5. **Add method**

### Student Enrollment Flow

1. Student visits course page (logged in)
2. Sees enrollment option with price and "Pay with Paddle" button
3. Clicks button → Paddle checkout opens (overlay)
4. Completes payment
5. Redirected back to Moodle
6. Webhook fires → User enrolled automatically
7. Student can access course immediately

## Testing

### Sandbox Mode Testing

1. Set **Environment** to `Sandbox`
2. Use **Sandbox API credentials** from Paddle
3. Create test course with Paddle enrollment
4. Use [Paddle test card numbers](https://developer.paddle.com/concepts/payment-methods/credit-debit-card#test-card-numbers):
   - Success: `4242 4242 4242 4242`
   - Decline: `4000 0000 0000 0002`

### Verify Webhook

Check webhook logs in Paddle Dashboard:
- **Developer Tools → Notifications → Event Logs**
- Status should be `200 OK`
- If errors, check Moodle logs

### Check Moodle Logs

```bash
# Via CLI
tail -f /path/to/moodledata/error.log

# Or via web
Site Administration → Reports → Logs
Filter by: enrol_paddle
```

## Troubleshooting

### Issue: "Script error for enrol_paddle/module"

**Cause:** AMD module not in correct location or cache not purged

**Fix:**
```bash
# Verify file exists
ls -la /path/to/moodle/enrol/paddle/amd/src/module.js

# Purge caches
php admin/cli/purge_caches.php
```

### Issue: "Paddle API key has not been configured"

**Cause:** API key not set or incorrect

**Fix:**
- Verify key is pasted correctly (no extra spaces)
- Check you're using the right environment key (sandbox vs live)
- Regenerate key in Paddle dashboard if needed

### Issue: Webhook signature verification failed

**Cause:** Webhook secret mismatch or wrong HMAC algorithm

**Fix:**
- Verify webhook secret matches Paddle dashboard
- Check HMAC algorithm setting matches Paddle (use SHA-256)
- Ensure webhook URL is exact: `https://yourdomain.com/enrol/paddle/ipn.php`

### Issue: User not enrolled after payment

**Cause:** Webhook not firing or failing

**Fix:**
1. Check Paddle Dashboard → Notifications → Event Logs
2. Check Moodle error logs
3. Verify webhook URL is publicly accessible (not localhost)
4. Check `enrol_paddle` table in database for transaction records

## Development

### Local Development Setup

```bash
# Clone repository
git clone https://github.com/imbowen1973/Paddle.git
cd Paddle

# Create feature branch
git checkout -b feature/your-feature-name

# Make changes...

# Test in local Moodle
ln -s $(pwd) /path/to/moodle/enrol/paddle
```

### Code Structure

```
paddle/
├── amd/src/              # AMD JavaScript modules
│   └── module.js         # Paddle checkout integration
├── classes/              # PHP classes
│   ├── privacy/
│   │   └── provider.php  # GDPR privacy provider
│   ├── task/
│   │   └── process_expirations.php  # Scheduled task
│   └── util.php          # Utility functions
├── db/                   # Database definitions
│   ├── access.php        # Capabilities
│   ├── install.xml       # Database schema
│   ├── messages.php      # Message providers
│   ├── services.php      # Web services
│   ├── tasks.php         # Scheduled tasks
│   └── upgrade.php       # Upgrade steps
├── lang/en/
│   └── enrol_paddle.php  # Language strings
├── templates/
│   └── enrol.mustache    # Enrollment page template
├── ipn.php               # Webhook handler
├── lib.php               # Main plugin class
├── return.php            # Return URL after payment
├── settings.php          # Admin settings
├── unenrolself.php       # Self-unenrollment
└── version.php           # Plugin version info
```

### Running Tests

```bash
# PHPUnit tests (when available)
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite enrol_paddle_testsuite

# Code checker
moodle-plugin-ci codechecker

# Validate plugin
moodle-plugin-ci validate
```

## Version History

### v1.0.0 (2025-10-27)
- Initial release
- Paddle Billing API integration
- HMAC webhook verification
- Multi-currency support
- Privacy API implementation
- AMD module for checkout
- Sandbox and Live environments

## Support

- **Issues:** [GitHub Issues](https://github.com/imbowen1973/Paddle/issues)
- **Documentation:** [Paddle Developer Docs](https://developer.paddle.com/)
- **Moodle Docs:** [Moodle Enrolment Plugins](https://docs.moodle.org/en/Enrolment_plugins)

## License

GNU GPL v3 or later

## Credits

- **Author:** Mark Bowen
- **Copyright:** 2025 Mark Bowen
- **Based on:** Moodle PayPal enrolment plugin structure

## Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Security

If you discover a security vulnerability, please email security@yourdomain.com instead of using the issue tracker.
