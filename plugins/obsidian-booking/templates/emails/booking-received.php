<?php
/**
 * Email: Booking Received — sent to USER.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name,
 *   $start_date, $end_date, $color, $total_price
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;">We've Received Your Request</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
Hello <?php echo esc_html( $first_name ); ?>, thank you for choosing Obsidian Reserve. Your reservation request is now under review.
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
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Estimated Total</td>
	<td style="padding:6px 0;font-size:14px;color:#c8a855;font-weight:600;">₱<?php echo esc_html( number_format( $total_price, 2 ) ); ?></td>
</tr>
</table>
</td>
</tr>
</table>

<h3 style="margin:0 0 12px;font-size:16px;color:#ffffff;">What happens next?</h3>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
<tr>
	<td style="padding:8px 0;font-size:14px;color:#cccccc;line-height:1.5;">
	<span style="color:#c8a855;font-weight:600;">1.</span> Our team reviews your submitted documents<br>
	<span style="color:#c8a855;font-weight:600;">2.</span> Once approved, you'll receive a secure payment link<br>
	<span style="color:#c8a855;font-weight:600;">3.</span> Complete your payment to confirm the reservation
	</td>
</tr>
</table>

<p style="margin:0;font-size:13px;color:#888888;line-height:1.5;">
We typically review submissions within 24 hours. You'll receive an email as soon as a decision is made.
</p>

<?php include __DIR__ . '/email-footer.php'; ?>
