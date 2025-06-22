TRUNCATE FROM engineer_stock;
TRUNCATE FROM inventory_dispatches;
TRUNCATE FROM inventory_dispatch_files;
TRUNCATE FROM inventory_dispatch_items;
TRUNCATE FROM material_requests;
TRUNCATE FROM material_request_items;
TRUNCATE FROM material_request_stock_transfers;
TRUNCATE FROM material_returns;
TRUNCATE FROM material_return_details;
TRUNCATE FROM material_return_items;
TRUNCATE FROM stock;
TRUNCATE FROM stock_in_transit;
TRUNCATE FROM stock_transfers;
TRUNCATE FROM stock_transactions;
TRUNCATE FROM stock_transfer_files;
TRUNCATE FROM stock_transfer_items;
TRUNCATE FROM stock_transfer_notes;


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

SET FOREIGN_KEY_CHECKS = 1;


php artisan make:migration update_status_to_material_requests_table --table=material_requests --path=database/migrations/V1



php artisan make:migration update_status_to_stock_transfers_table --table=material_requests --path=database/migrations/V1