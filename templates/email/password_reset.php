<p>Hello <?php echo htmlspecialchars($name ?? 'User'); ?>,</p>

<p>We received a request to reset your password. Click the button below to create a new password:</p>

<table style="margin:30px 0;">
    <tr>
        <td style="background:#FBBF24;border-radius:8px;">
            <a href="<?php echo $reset_link; ?>" style="display:block;padding:14px 30px;color:#1E3A8A;text-decoration:none;font-weight:600;text-align:center;">
                Reset Password
            </a>
        </td>
    </tr>
</table>

<p style="font-size:14px;color:#6b7280;">This link will expire in 1 hour.</p>

<p style="font-size:14px;color:#6b7280;">If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>