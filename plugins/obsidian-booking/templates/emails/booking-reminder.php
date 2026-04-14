<?php
/**
 * Email: Pickup Reminder — sent to USER 24h before start date.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name,
 *   $start_date, $end_date, $color, $location,
 *   $delivery_dropoff, $delivery_date, $delivery_time
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;text-align:center;">Your Pickup Is Tomorrow!</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;text-align:center;">
Hello <?php echo esc_html( $first_name ); ?>, just a friendly reminder that your <strong style="color:#ffffff;"><?php echo esc_html( $car_name ); ?></strong> reservation starts tomorrow.
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
	<td style="padding:6px 0;font-size:13px;color:#888888;">Pick-up Date</td>
	<td style="padding:6px 0;font-size:14px;color:#c8a855;font-weight:600;"><?php echo esc_html( $start_date ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Return Date</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $end_date ); ?></td>
</tr>
<?php if ( ! empty( $delivery_dropoff ) ) : ?>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Delivery To</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $delivery_dropoff ); ?></td>
</tr>
<?php endif; ?>
<?php if ( ! empty( $delivery_date ) && ! empty( $delivery_time ) ) : ?>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Delivery Time</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $delivery_date ); ?> at <?php echo esc_html( $delivery_time ); ?></td>
</tr>
<?php endif; ?>
<?php if ( ! empty( $location ) ) : ?>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Location</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $location ); ?></td>
</tr>
<?php endif; ?>
</table>
</td>
</tr>
</table>

<h3 style="margin:0 0 12px;font-size:16px;color:#ffffff;">Don't forget to bring:</h3>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
<tr>
<td style="padding:4px 0;font-size:14px;color:#cccccc;">• Valid driver's license</td>
</tr>
<tr>
<td style="padding:4px 0;font-size:14px;color:#cccccc;">• Government-issued ID</td>
</tr>
<?php if ( $payment_option === 'down' ) : ?>
<tr>
<td style="padding:4px 0;font-size:14px;color:#cccccc;">• Remaining balance payment</td>
</tr>
<?php endif; ?>
</table>

<p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;text-align:center;">
See you tomorrow! Drive safe.
</p>

<?php include __DIR__ . '/email-footer.php'; ?>
