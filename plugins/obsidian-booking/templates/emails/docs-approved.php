<?php
/**
 * Email: Documents Approved — sent to USER.
 *
 * Available variables:
 *   $booking_id, $car_name, $first_name,
 *   $start_date, $end_date, $color, $total_price, $payment_url
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/email-header.php';
?>

<h2 style="margin:0 0 8px;font-size:20px;color:#ffffff;">Documents Approved</h2>
<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
Hello <?php echo esc_html( $first_name ); ?>, great news! Your documents for <strong style="color:#ffffff;"><?php echo esc_html( $car_name ); ?></strong> have been reviewed and approved.
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
	<td style="padding:6px 0;font-size:13px;color:#888888;">Total Due</td>
	<td style="padding:6px 0;font-size:14px;color:#c8a855;font-weight:600;">₱<?php echo esc_html( number_format( $total_price, 2 ) ); ?></td>
</tr>
</table>
</td>
</tr>
</table>

<p style="margin:0 0 24px;font-size:15px;color:#cccccc;line-height:1.6;">
Please complete your payment to confirm your reservation. You can choose between a 50% down payment or full prepayment.
</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
<tr>
<td style="background-color:#c8a855;border-radius:6px;">
<a href="<?php echo esc_url( $payment_url ); ?>" style="display:inline-block;padding:14px 40px;font-size:15px;font-weight:600;color:#111111;text-decoration:none;">Complete Payment</a>
</td>
</tr>
</table>

<p style="margin:0;font-size:12px;color:#666666;line-height:1.5;text-align:center;">
This payment link is unique to your booking. Please do not share it with others.
</p>

<?php include __DIR__ . '/email-footer.php'; ?>
