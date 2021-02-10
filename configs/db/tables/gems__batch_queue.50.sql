CREATE TABLE `gems__batch_queue` (
    `gbq_id_item` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `gbq_id_batch` varchar(255) COLLATE 'utf8_general_ci' NOT NULL,
    `gbq_item_name` varchar(255) COLLATE 'utf8_general_ci' NULL,
    `gbq_priority` smallint unsigned NOT NULL DEFAULT '20',
    `gbq_delay_until` datetime DEFAULT NULL,
    `gbq_command` json NOT NULL,
    `gbq_changed` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    `gbq_changed_by` bigint(20) NOT NULL,
    `gbq_created` timestamp NOT NULL,
    `gbq_created_by` bigint(20) NOT NULL
);
