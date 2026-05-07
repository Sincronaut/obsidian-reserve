<?php
/**
 * Email: Booking Cancelled (User-Initiated) — sent to USER + ADMIN.
 *
 * Available variables:
 *   $booking_reference, $car_name, $first_name,
 *   $start_date, $end_date, $color
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;">Booking Cancelled</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
Hello <?php echo esc_html( $first_name ); ?>, your reservation for <strong style="color:#ffffff;"><?php echo esc_html( $car_name ); ?></strong> has been cancelled as requested.
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#222222;border-radius:8px;margin-bottom:24px;">
<tr>
<td style="padding:20px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;width:140px;">Booking ID</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;font-weight:600;"><?php echo esc_html( $booking_reference ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Vehicle</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $car_name ); ?> (<?php echo esc_html( $color ); ?>)</td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Dates</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $start_date ); ?> — <?php echo esc_html( $end_date ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Status</td>
	<td style="padding:6px 0;font-size:14px;color:#95A5A6;font-weight:600;">Cancelled</td>
</tr>
</table>
</td>
</tr>
</table>

<p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;">
If you'd like to book again in the future, feel free to visit our fleet page and submit a new reservation.
</p>

<?php require __DIR__ . '/email-footer.php'; ?>
