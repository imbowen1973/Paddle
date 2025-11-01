define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    var PaddleCheckout = {
        init: function(args) {
            var btn = document.getElementById('paddle-checkout');
            if (!btn) {
                return;
            }

            try {
                if (typeof Paddle !== 'undefined') {
                    if (typeof Paddle.Environment !== 'undefined') {
                        if (args.environment === 'sandbox') {
                            Paddle.Environment.set('sandbox');
                        }
                    }
                }
            } catch (e) {
                // Ignore setup errors; they will surface on checkout open.
                console.error('Paddle setup error:', e);
            }

            btn.addEventListener('click', function() {
                if (btn.dataset.loading) {
                    return;
                }
                btn.dataset.loading = '1';
                btn.setAttribute('disabled', 'disabled');

                console.log('Paddle Checkout: Calling AJAX with args:', args);
                console.log('Paddle Checkout: instanceid =', args.instanceid, '(type:', typeof args.instanceid, ')');

                Ajax.call([
                    {
                        methodname: 'enrol_paddle_get_checkout_id',
                        args: {
                            instanceid: parseInt(args.instanceid, 10)
                        }
                    }
                ])[0].then(function(data) {
                    // ALWAYS log debug info to console
                    if (data && data.debug && data.debug.console_log) {
                        console.group('=== PADDLE EXECUTION LOG ===');
                        console.log(data.debug.console_log);
                        console.groupEnd();
                    }

                    // Additional debug logging if available.
                    if (data && data.debug) {
                        console.group('Paddle Checkout Debug');
                        if (data.debug.endpoint) console.log('Endpoint:', data.debug.endpoint);
                        if (data.debug.response_code) console.log('HTTP Code:', data.debug.response_code);
                        if (data.debug.payload) {
                            try {
                                console.log('Request Payload:', JSON.parse(data.debug.payload));
                            } catch (e) {
                                console.log('Request Payload:', data.debug.payload);
                            }
                        }
                        if (data.debug.response_body) console.log('Response Body:', data.debug.response_body);
                        console.groupEnd();
                    }

                    if (!data || !data.success || !data.checkout_id) {
                        throw new Error(data && data.error ? data.error : 'Invalid response from Moodle');
                    }
                    if (typeof Paddle === 'undefined' || typeof Paddle.Checkout === 'undefined') {
                        throw new Error('Paddle.js not loaded or initialized correctly.');
                    }
                    Paddle.Checkout.open({
                        checkoutId: data.checkout_id
                    });
                    delete btn.dataset.loading;
                    btn.removeAttribute('disabled');
                }).catch(function(error) {
                    console.error('Paddle checkout error:', error);

                    // Extract user-friendly error message
                    var userMessage = args.checkoutcreationfailed;
                    if (error.message) {
                        // Show the actual error message from Moodle if available
                        userMessage = error.message;
                    }

                    Notification.exception({
                        message: userMessage,
                        err: error
                    });
                    delete btn.dataset.loading;
                    btn.removeAttribute('disabled');
                });
            });
        }
    };
    return PaddleCheckout;
});
