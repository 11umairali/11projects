-- Run once in phpMyAdmin if you cannot run "php artisan migrate"
ALTER TABLE `users` ADD COLUMN `last_login_ip` VARCHAR(45) NULL AFTER `signup_ip`;
