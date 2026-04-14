<?php
/**
 * Email: Booking Submitted — sent to ADMIN.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name, $last_name,
 *   $start_date, $end_date, $color, $customer_type,
 *   $total_price, $admin_url
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 20px;font-size:20px;color:#ffffff;">New Booking Submitted</h2>

<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
A new reservation request has been submitted and is awaiting your review.
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
	<td style="padding:6px 0;font-size:13px;color:#888888;">Customer</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( "$first_name $last_name" ); ?></td>
</tr>
<tr>
	<td style="padding:6px 0;font-size:13px;color:#888888;">Type</td>
	<td style="padding:6px 0;font-size:14px;color:#ffffff;"><?php echo esc_html( ucfirst( $customer_type ) ); ?> Renter</td>
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
	<td style="padding:6px 0;font-size:13px;color:#888888;">Total Price</td>
	<td style="padding:6px 0;font-size:14px;color:#c8a855;font-weight:600;">₱<?php echo esc_html( number_format( $total_price, 2 ) ); ?></td>
</tr>
</table>
</td>
</tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
<tr>
<td style="background-color:#c8a855;border-radius:6px;">
<a href="<?php echo esc_url( $admin_url ); ?>" style="display:inline-block;padding:12px 32px;font-size:14px;font-weight:600;color:#111111;text-decoration:none;">Review Booking</a>
</td>
</tr>
</table>

<?php include __DIR__ . '/email-footer.php'; ?>
