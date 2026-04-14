<?php
/**
 * Email: Booking Confirmed — sent to USER.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name,
 *   $start_date, $end_date, $color, $total_price,
 *   $payment_amount, $payment_option, $location,
 *   $delivery_dropoff, $delivery_date, $delivery_time
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<div style="text-align:center;margin-bottom:24px;">
<div style="display:inline-block;width:56px;height:56px;background-color:#1b3a1b;border-radius:50%;line-height:56px;font-size:28px;color:#4caf50;">✓</div>
</div>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;text-align:center;">Reservation Confirmed</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;text-align:center;">
Hello <?php echo esc_html( $first_name ); ?>, your reservation has been confirmed! Here are your booking details.
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
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $start_date ); ?></td>
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
<?php if ( ! empty( $delivery_date ) ) : ?>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Delivery Date</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( $delivery_date ); ?> <?php echo ! empty( $delivery_time ) ? esc_html( $delivery_time ) : ''; ?></td>
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

<!-- Payment receipt -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#222222;border-radius:8px;margin-bottom:24px;">
<tr>
<td style="padding:20px;">
<p style="margin:0 0 12px;font-size:14px;color:#c8a855;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Payment Receipt</p>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
	<td style="padding:6px 0;font-size:14px;color:#cccccc;">Rental Total</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;text-align:right;">₱<?php echo esc_html( number_format( $total_price, 2 ) ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:14px;color:#cccccc;">Amount Paid</td>
	<td style="padding:6px 0;font-size:14px;color:#4caf50;text-align:right;font-weight:600;">₱<?php echo esc_html( number_format( $payment_amount, 2 ) ); ?></td>
</tr>
<?php if ( $payment_option === 'down' ) : ?>
<tr>
	<td style="padding:6px 0;font-size:14px;color:#cccccc;">Balance Due at Pickup</td>
	<td style="padding:6px 0;font-size:14px;color:#ff9800;text-align:right;">₱<?php echo esc_html( number_format( $total_price - $payment_amount, 2 ) ); ?></td>
</tr>
<?php endif; ?>
</table>
</td>
</tr>
</table>

<p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;text-align:center;">
We look forward to seeing you. Drive safe!
</p>

<?php include __DIR__ . '/email-footer.php'; ?>
