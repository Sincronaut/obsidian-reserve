<?php
/**
 * Email: Booking Denied — sent to USER.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name,
 *   $start_date, $end_date, $color, $denial_reason
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;">Update on Your Reservation</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
Hello <?php echo esc_html( $first_name ); ?>, we've reviewed your reservation request for <strong style="color:#ffffff;"><?php echo esc_html( $car_name ); ?></strong> and unfortunately we're unable to approve it at this time.
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#222222;border-radius:8px;margin-bottom:24px;">
<tr>
<td style="padding:20px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;width:140px;">Booking ID</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;font-weight:600;">#<?php echo esc_html( $booking_id ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Vehicle</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $car_name ); ?> (<?php echo esc_html( $color ); ?>)</td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Dates</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $start_date ); ?> — <?php echo esc_html( $end_date ); ?></td>
</tr>
</table>
</td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#2a1a1a;border-left:3px solid #cc4444;border-radius:0 8px 8px 0;margin-bottom:24px;">
<tr>
<td style="padding:16px 20px;">
<p style="margin:0 0 4px;font-size:12px;color:#cc4444;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Reason</p>
<p style="margin:0;font-size:14px;color:#cccccc;line-height:1.5;"><?php echo esc_html( $denial_reason ); ?></p>
</td>
</tr>
</table>

<p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;">
If you believe this was made in error or have additional documents to provide, please feel free to submit a new reservation request on our website.
</p>

<?php include __DIR__ . '/email-footer.php'; ?>
