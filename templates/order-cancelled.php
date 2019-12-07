<!DOCTYPE html
	PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Demystifying Email Design</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body style="background-image: url('http://139.180.219.139/shop/api/public/assets/assets/images/bg.jpeg');margin: 0; padding: 0;">
	<table border="0" cellpadding="0" cellspacing="0" width="100%">
		<tr>
			<td style="padding: 10px 0 30px 0;">
				<table align="center" border="0" cellpadding="0" cellspacing="0" width="600"
					style="border: 1px solid #cccccc; border-collapse: collapse;">
					<tr>
						<td align="center" bgcolor="#70bbd9"
							style="padding: 0px 0 0px 0; color: #153643; font-size: 28px; font-weight: bold; font-family: Arial, sans-serif;">
							<img src="http://139.180.219.139/shop/api/public/assets/assets/images/order-canceled.jpeg" alt="Creating Email Magic" width="100%" height="100%"
								style="display: block;" />
						</td>
					</tr>
					<tr>
						<td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
							<table border="0" cellpadding="0" cellspacing="0" width="100%">
								<tr>
									<td
										style="color: #153643; font-family: Arial, sans-serif; font-size: 24px;text-align: center;">
										<b>PESANAN DIBATALKAN</b>
									</td>
								</tr>
								<tr>
									<td
										style="padding: 20px 0 30px 0; color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 20px;">
										Dear <?=$name?> <br />
										Pesanan anda pada dengan nomor transaksi <?=$transaction_number?> telah dibatalkan secara otomatis pada tanggal <?=$order_cancellation_date?> karena sudah melewati batas 24 jam. <br />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td bgcolor="lightgray" style="padding: 30px 30px 30px 30px;">
							<table border="0" cellpadding="0" cellspacing="0" width="100%">
								<tr>
									<td style="color: #000; font-family: Arial, sans-serif; font-size: 14px;"
										width="75%">
										Catatan: <br />
										1. Email ini dikirim oleh sistem mohon untuk tidak membalas. <br>
										2. Untuk pesanan berikutnya silahkan lanjutkan belanja
									</td>
									<!-- <td align="right" width="25%">
											<table border="0" cellpadding="0" cellspacing="0">
												<tr>
													<td style="font-family: Arial, sans-serif; font-size: 12px; font-weight: bold;">
														<a href="http://www.twitter.com/" style="color: #ffffff;">
															<img src="https://s3-us-west-2.amazonaws.com/s.cdpn.io/210284/tw.gif" alt="Twitter" width="38" height="38" style="display: block;" border="0" />
														</a>
													</td>
													<td style="font-size: 0; line-height: 0;" width="20">&nbsp;</td>
													<td style="font-family: Arial, sans-serif; font-size: 12px; font-weight: bold;">
														<a href="http://www.twitter.com/" style="color: #ffffff;">
															<img src="https://s3-us-west-2.amazonaws.com/s.cdpn.io/210284/fb.gif" alt="Facebook" width="38" height="38" style="display: block;" border="0" />
														</a>
													</td>
												</tr>
											</table>
										</td> -->
								</tr>
							</table>
							<br>
							<table width="100%" style="color: #000; font-family: Arial, sans-serif; font-size: 14px;">
								<tr>
									<td style="float: right;">
										<a href="<?=$app_url?>" style="background-color: #000; border: none; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;">
											CONTINUE SHOPPING &#8594;
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>

</html>