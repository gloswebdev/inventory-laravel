-- ============================================================
-- InvoFlow — app_settings TABLE PATCH
-- Hostinger phpMyAdmin mein import karo
-- Database: u293228258_invoflow5
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Default Data Insert
-- ============================================================

INSERT IGNORE INTO `app_settings` (`key`, `value`, `label`, `group`, `created_at`, `updated_at`) VALUES
('erp_api_base_url',          'https://logicapi.algebraerp.com/API/SYNWOOD', 'ERP Base URL',           'erp_api',              NOW(), NOW()),
('erp_api_key',               'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14',      'API Key',                'erp_api',              NOW(), NOW()),
('inventory_api_branch',      'ALL',                                          'Branch (Inventory API)', 'inventory_api',        NOW(), NOW()),
('inventory_api_item',        'ALL',                                          'Item (Inventory API)',   'inventory_api',        NOW(), NOW()),
('factory_stock_branch',      '2',                                            'Factory Branch Code',    'inventory_api',        NOW(), NOW()),
('product_master_itemdetcode','0',                                            'Itemdetcode',            'product_master_api',   NOW(), NOW()),
('product_master_usercode',   '0',                                            'Usercode',               'product_master_api',   NOW(), NOW()),
('product_master_branchcode', '0',                                            'Branchcode',             'product_master_api',   NOW(), NOW()),
('product_master_page_number','1',                                            'PageNumber',             'product_master_api',   NOW(), NOW()),
('product_master_rows',       '10000',                                        'RowsOfPage',             'product_master_api',   NOW(), NOW()),
('product_master_txn_type',   'Old',                                          'TxnType',                'product_master_api',   NOW(), NOW());

-- ============================================================
-- Verify
-- ============================================================
SELECT COUNT(*) as 'app_settings rows imported' FROM `app_settings`;
