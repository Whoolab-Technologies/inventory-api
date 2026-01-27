SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE engineer_stock;
TRUNCATE TABLE inventory_dispatches;
TRUNCATE TABLE inventory_dispatch_files;
TRUNCATE TABLE inventory_dispatch_items;

TRUNCATE TABLE material_request_stock_transfers;
TRUNCATE TABLE material_returns;
TRUNCATE TABLE material_return_details;
TRUNCATE TABLE material_return_items;
TRUNCATE TABLE material_return_files;

TRUNCATE TABLE stock;
TRUNCATE TABLE stock_metas;
TRUNCATE TABLE stock_in_transit;
TRUNCATE TABLE stock_transfers;
TRUNCATE TABLE stock_transactions;
TRUNCATE TABLE stock_transfer_files;
TRUNCATE TABLE stock_transfer_items;
TRUNCATE TABLE stock_transfer_notes;


TRUNCATE TABLE material_requests;
TRUNCATE TABLE material_request_items;
TRUNCATE TABLE material_request_files;

TRUNCATE TABLE purchase_requests;
TRUNCATE TABLE purchase_request_items;

TRUNCATE TABLE lpos;
TRUNCATE TABLE lpo_items;
TRUNCATE table locations;
TRUNCATE TABLE lpo_shipments;
TRUNCATE TABLE lpo_shipment_items;

SET FOREIGN_KEY_CHECKS = 1;
-- ------ for development purpose -----

ALTER TABLE `inventory_dispatches` CHANGE `delivery_note_number` `dn_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
php artisan make:migration _table  --table=stock_transfers  --path=database/migrations/V1
php artisan make:migration create_prs_table --path=database/migrations/V1
php artisan make:migration create_items_table --path=database/migrations/V1

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE brands;
TRUNCATE TABLE categories;
TRUNCATE TABLE departments;
TRUNCATE TABLE engineer_stock;
TRUNCATE TABLE engineers;
TRUNCATE TABLE failed_jobs;
TRUNCATE TABLE inventory_dispatch_files;
TRUNCATE TABLE inventory_dispatch_items;
TRUNCATE TABLE inventory_dispatches;
TRUNCATE TABLE material_request_items;
TRUNCATE TABLE material_request_stock_transfers;
TRUNCATE TABLE material_requests;
TRUNCATE TABLE material_return_details;
TRUNCATE TABLE material_return_items;
TRUNCATE TABLE material_returns;
TRUNCATE TABLE password_reset_tokens;
TRUNCATE TABLE personal_access_tokens;
TRUNCATE TABLE products;
TRUNCATE TABLE stock;
TRUNCATE TABLE stock_in_transit;
TRUNCATE TABLE stock_metas;
TRUNCATE TABLE stock_transactions;
TRUNCATE TABLE stock_transfer_files;
TRUNCATE TABLE stock_transfer_items;
TRUNCATE TABLE stock_transfer_notes;
TRUNCATE TABLE stock_transfers;
TRUNCATE TABLE storekeepers;
TRUNCATE TABLE stores;
TRUNCATE TABLE units;
TRUNCATE TABLE users;
TRUNCATE TABLE purchase_requests;
TRUNCATE TABLE purchase_request_items;
TRUNCATE TABLE lpos;
TRUNCATE TABLE lpo_shipments;
TRUNCATE TABLE lpo_shipment_items

SET FOREIGN_KEY_CHECKS = 1;


php artisan make:migration update_status_to_material_requests_table --table=material_requests --path=database/migrations/V1

ALTER TABLE `stock_transactions`
  DROP `type`;

php artisan make:migration add_return_number_to_material_returns_table --table=material_returns --path=database/migrations/V1/updates



INSERT INTO `brands` (`id`, `name`, `description`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(4, 'DECODUCT', NULL, 3, 'admin', 3, 'admin', '2025-06-14 08:12:07', '2025-06-14 08:12:07'),
(5, 'LEWDEN', NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:02:55', '2025-06-14 12:02:55'),
(6, 'PALAZZOLI', NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:03:05', '2025-06-14 12:12:00'),
(7, 'CRAIG&DERICOTT', NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:10:29', '2025-06-14 12:10:29'),
(8, 'GEWISS', NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:15:32', '2025-06-14 12:15:32'),
(9, 'N/A', 'LOCAL BRANDS', 3, 'admin', 3, 'admin', '2025-06-15 10:42:15', '2025-06-15 10:42:26');


INSERT INTO `admins` (`id`, `name`, `email`, `password`, `remember_token`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'Vishnu', 'vishnu@whoolab.com', '$2y$12$CiYNd621IIcxbe8/1Z/rQOsD6PS7cgH7mfA6f/jtSYE3mg1mDNgh6', NULL, NULL, NULL, NULL, NULL, '2025-02-23 23:46:01', '2025-06-12 15:56:04'),
(3, 'Store Lead', 'storelead@laithllc.com', '$2y$12$ddsWArHhHBWqthQyKCFvF.pE3VeNX4encP6cu6cZGm6LOhTLkotxe', NULL, 1, 'admin', 1, 'admin', '2025-06-12 07:55:39', '2025-06-12 07:55:39');


INSERT INTO `units` (`id`, `name`, `symbol`, `created_at`, `updated_at`) VALUES
(1, 'PIECES', 'PCS', '2025-06-14 08:12:32', '2025-06-14 08:12:32');


INSERT INTO `stores` (`id`, `name`, `location`, `type`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'CENTRAL STORE', 'ICAD 3', 'central', 3, 'admin', 3, 'admin', '2025-06-12 15:22:47', '2025-06-12 15:22:47'),
(2, '25001 AUH-2.6-ISP & SECUIRITY (KDC)', 'ABUDHABI', 'site', 3, 'admin', 3, 'admin', '2025-06-14 09:54:15', '2025-06-14 09:54:15'),
(3, '24003-EQUIPMENT REPLACEMENT-IRIS 5-7(KDC)', 'KEZAD B', 'site', 3, 'admin', 3, 'admin', '2025-06-14 10:20:05', '2025-06-14 10:20:05');


INSERT INTO `storekeepers` (`id`, `first_name`, `last_name`, `email`, `password`, `remember_token`, `store_id`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'NAIZAM', 'K K', 'store@laithllc.com', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', NULL, 1, 3, 'admin', 3, 'admin', '2025-06-12 16:05:40', '2025-06-12 16:05:40'),
(6, 'MOHAMMED', 'KHALID', 'store@laithllc.com1', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', NULL, 2, 3, 'admin', 3, 'admin', '2025-06-14 09:56:39', '2025-06-14 09:56:39'),
(8, 'JOPHY', 'RAPHAEL', 'store@laithllc.com2', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', NULL, 3, 3, 'admin', 3, 'admin', '2025-06-14 10:21:44', '2025-06-14 10:21:44');

INSERT INTO `products` (`id`, `item`, `cat_id`, `description`, `unit_id`, `category_id`, `brand_id`, `min_stock_qty`, `qr_code`, `image`, `remarks`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, '20 MM PVC CONDUIT HEAVY DUTY', 'LEM-1', '3 MTR LENGTH', 1, 1, 4, 10, 'qrcodes/products/01/1.png', NULL, NULL, 3, 'admin', 1, 'admin', '2025-06-14 08:13:04', '2025-06-17 07:49:14'),
(2, 'WALL MOUNTED SOCKETS 90 DEG ANGLED 50-60HZ IP66/IP67', 'LEM-2', NULL, 1, 2, 6, 0, 'qrcodes/products/02/2.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:11:16', '2025-06-15 11:07:47'),
(3, 'INTERLOCKED METAL SOCKET 32A 2P+E 220V PD32/301FPB', 'LEM-3', NULL, 1, 2, 5, 0, 'qrcodes/products/03/3.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:13:34', '2025-06-14 12:13:34'),
(4, '460246LW PD32/344FPB INTERLOCKED METAL SOCKET 3P+N+E 32A 380V', 'LEM-4', NULL, 1, 2, 5, 0, 'qrcodes/products/04/4.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-14 12:14:58', '2025-06-14 12:14:58'),
(5, 'GW62505 3P+N+E 90 DEG ANGLED SURFACE MOUNTING SOCKET OUTLET IP67 3P+N+E 16A 380-415V', 'LEM-5', NULL, 1, 2, 8, 0, 'qrcodes/products/05/5.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:37:28', '2025-06-15 10:37:29'),
(6, '5X32A 3P+N+E IP44 MALE INDUSTRIAL PLUG IP44 415V', 'LEM-6', NULL, 1, 2, 9, 0, 'qrcodes/products/06/6.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:43:12', '2025-06-15 10:43:12'),
(7, '5X32A 3P+N+E IP44 FEMALE INDUSTRIAL SOCKET 415V IP44', 'LEM-7', NULL, 1, 2, 9, 0, 'qrcodes/products/07/7.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:44:05', '2025-06-15 10:44:05'),
(8, 'EDDKG403NLX 40A TP + NL DIE CAST ALUMINIUM IP66 WITH BOTOOM ENTRY AND EXIT', 'LEM-8', NULL, 1, 3, 7, 0, 'qrcodes/products/08/8.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:45:35', '2025-06-15 10:45:35'),
(9, '16A 3P+N+E INDUSTRIAL MALE PLUG', 'LEM-9', NULL, 1, 2, 9, 0, 'qrcodes/products/09/9.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:47:34', '2025-06-15 10:47:34'),
(10, '16A 3P+N+E INDUSTRIAL FEMALE SOCKET', 'LEM-10', NULL, 1, 2, 9, 0, 'qrcodes/products/10/10.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:48:01', '2025-06-15 10:48:01'),
(11, 'P451246LW-SKT SWIT 32A 415V 5P 6H MOULDED (PM32/3408NFPB)', 'LEM-11', NULL, 1, 2, 9, 0, 'qrcodes/products/11/11.png', NULL, NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:48:36', '2025-06-15 10:48:36');

INSERT INTO `engineers` (`id`, `first_name`, `last_name`, `email`, `password`, `store_id`, `department_id`, `remember_token`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'VAISHNAV', 'C', 'storelead@laithllc.com', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', 2, 1, NULL, 3, 'admin', 3, 'admin', '2025-06-12 17:13:23', '2025-06-15 12:41:38'),
(2, 'VEMBILKAR', 'V JAMES', 'vembilkar@laithllc.com', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', 2, 1, NULL, 3, 'admin', 3, 'admin', '2025-06-15 12:00:04', '2025-06-15 12:00:04'),
(3, 'MOHAMMED', 'RIZWAN', 'rizwan@laithllc.com', '$2y$12$4w5qjNNorI5.siikJ6CA0.NnXhQn0rRrvR9EvGDBg3qqD9Ml.2GtK', 2, 2, NULL, 3, 'admin', 3, 'admin', '2025-06-15 12:58:14', '2025-06-15 12:58:14');

INSERT INTO `departments` (`id`, `name`, `description`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'ELECTRICAL', NULL, 3, 'admin', 3, 'admin', '2025-06-12 16:19:10', '2025-06-12 16:19:10'),
(2, 'MECHANICAL', NULL, 3, 'admin', 3, 'admin', '2025-06-15 12:56:14', '2025-06-15 12:56:14');

INSERT INTO `categories` (`id`, `category_id`, `name`, `description`, `created_by`, `created_type`, `updated_by`, `updated_type`, `created_at`, `updated_at`) VALUES
(1, 'LEM-CT-1', 'PVC PIPES AND FITTINGS', NULL, 3, 'admin', 1, 'admin', '2025-06-12 16:16:10', '2025-06-14 09:21:42'),
(2, 'LEM-CT-2', 'INDUSTRIAL PLUGS AND SOCKETS', NULL, 3, 'admin', 3, 'admin', '2025-06-14 11:32:44', '2025-06-14 12:00:44'),
(3, 'LEM-CT-3', 'ISOLATORS', NULL, 3, 'admin', 3, 'admin', '2025-06-15 10:44:29', '2025-06-15 10:44:29');


ALTER TABLE `stock_transactions` CHANGE `type` `type` ENUM('DIRECT','MR','PR','SS-RETURN','ENGG-RETURN','DISPATCH') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DIRECT';
php artisan make:migration create_lpo__files_table --create=lpo_files --path=database/migrations/V1/pr

php artisan make:model V1/LpoItems
php artisan make:controller V1/SupplierController
php artisan make:migration create_product_min_stocks_table  --create=product_min_stocks --path=database/migrations/V1/pr

php artisan make:migration add_is_store_transfer_to_stock_transfers_table --table=stock_transfers --path=database/migrations/V1/pr
