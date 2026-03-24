<?php
/** 
 * daftar_pengguna.php
 * - Papar senarai pengguna sistem eCetak
 * - Merujuk kepada table 'users' dalam database ecetak
*/

// Include database connection (ecetak database)
require_once __DIR__ . '/config/db.php';

// Include database connection (idata database)
require_once __DIR__ . '/config/db_idata.php';

?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Pengguna - eCetak</title>
  <link rel="icon" href="assets/favicon.ico">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
		* {
				margin: 0;
				padding: 0;
				box-sizing: border-box;
		}

		body {
				background: linear-gradient(135deg, #001f3f 0%, #004080 50%, #0066cc 100%);
				min-height: 100vh;
				font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
		}

		.container-wrapper {
				min-height: 100vh;
				padding: 30px 15px;
				dislplay: flex;
				flex-direction: column;
				justify-content: flex-start;
		}

		.page-title {
			color: white;
			text-align: center;
			margin-bottom: 30px;
			padding: 20px;
			text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
		}

		.page-title h1 {
			font-weight: 700;
			font-size: 2.5em;
			margin-bottom: 10px;
		}
	</style>
</head>
<body>
	<div class="container-wrapper">
		<!-- Page Title -->
		<div class="page-title">
			<h1><i class="fas fa-users text-white me-2"></i>Daftar Pengguna</h1>
		</div>
		