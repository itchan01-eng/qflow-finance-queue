
<?php

require_once __DIR__ . '/send-email.php';

function qflow_send_verification_email(
    PDO $pdo,
    int $userId
): bool {

    try {
        $stmt = $pdo->prepare('
            SELECT
                user_id,
                first_name,
                email,
                email_verified_at
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ');

        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$user ||
            !empty($user['email_verified_at']) ||
            empty($user['email'])
        ) {
            return false;
        }

        $appUrl = rtrim(
            (string) getenv('QUEUEFLOW_APP_URL'),
            '/'
        );

        if ($appUrl === '' || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            error_log('QueueFlow: Invalid or missing QUEUEFLOW_APP_URL.');
            return false;
        }

        // Generate a secure token and store only its hash.
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (30 * 60)
        );

        $pdo->beginTransaction();

        // Invalidate previous tokens.
        $delete = $pdo->prepare('
            DELETE FROM email_verification_tokens
            WHERE user_id = ?
        ');

        $delete->execute([$userId]);

        // Save the new token.
        $insert = $pdo->prepare('
            INSERT INTO email_verification_tokens (
                user_id,
                token_hash,
                expires_at
            ) VALUES (?, ?, ?)
        ');

        $insert->execute([
            $userId,
            $tokenHash,
            $expiresAt
        ]);

        $pdo->commit();

        $verificationLink = $appUrl .
            '/verify-email.php?token=' .
            urlencode($token);

        $safeName = htmlspecialchars(
            $user['first_name'],
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLink = htmlspecialchars(
            $verificationLink,
            ENT_QUOTES,
            'UTF-8'
        );

        $message = '
        <!DOCTYPE html>
        <html>
        <body style="margin:0;padding:30px 15px;background:#f7f7f9;font-family:Arial,sans-serif;color:#202124;">

            <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9e9ed;">

                <div style="padding:28px 30px;text-align:center;border-bottom:1px solid #e9e9ed;">
                    <h2 style="margin:0;color:#c8102e;">QueueFlow</h2>
                    <p style="margin:8px 0 0;color:#737780;font-size:13px;">
                        RMMC-MI Finance Queuing Management System
                    </p>
                </div>

                <div style="padding:35px 30px;">

                    <p style="font-size:14px;color:#c8102e;font-weight:bold;letter-spacing:1px;">
                        EMAIL VERIFICATION
                    </p>

                    <h2 style="font-size:24px;margin:10px 0 18px;">
                        Verify your email address
                    </h2>

                    <p style="font-size:15px;line-height:1.7;color:#555;">
                        Hello ' . $safeName . ',
                    </p>

                    <p style="font-size:15px;line-height:1.7;color:#555;">
                        Please verify your email address to complete your QueueFlow account registration and access the system.
                    </p>

                    <div style="text-align:center;margin:30px 0;">
                        <a href="' . $safeLink . '"
                           style="display:inline-block;background:#c8102e;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:6px;font-weight:bold;">
                            Verify Email Address
                        </a>
                    </div>

                    <p style="font-size:13px;line-height:1.7;color:#737780;">
                        This verification link will expire in 30 minutes.
                    </p>

                    <p style="font-size:13px;line-height:1.7;color:#737780;">
                        If the button does not work, copy and paste this link into your browser:
                        <br>
                        <a href="' . $safeLink . '" style="color:#c8102e;word-break:break-all;">
                            ' . $safeLink . '
                        </a>
                    </p>

                    <p style="font-size:13px;line-height:1.7;color:#737780;">
                        If you did not request this email, you can safely ignore it.
                    </p>

                </div>

                <div style="padding:20px 30px;background:#f7f7f9;text-align:center;">
                    <p style="margin:0;font-size:12px;color:#737780;">
                        QueueFlow · RMMC-MI Finance Queuing Management System
                    </p>
                </div>

            </div>

        </body>
        </html>';

        $sent = sendEmail(
            $user['email'],
            'QueueFlow: Verify Your Email Address',
            $message
        );

        if (!$sent) {
            // Remove the unusable token if email sending fails.
            $deleteFailed = $pdo->prepare('
                DELETE FROM email_verification_tokens
                WHERE user_id = ?
                  AND token_hash = ?
            ');

            $deleteFailed->execute([
                $userId,
                $tokenHash
            ]);

            return false;
        }

        return true;

    } catch (Throwable $exception) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'QueueFlow verification email error: ' .
            $exception->getMessage()
        );

        return false;
    }
}
