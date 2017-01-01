DELETE FROM #__eshop_payments WHERE name = 'eshop_mellat';

INSERT INTO `#__eshop_payments`(`id`, `name`, `title`, `author`, `creation_date`, `copyright`, `license`, `author_email`, `author_url`, `version`, `description`, `params`, `ordering`, `published`) VALUES 
(21, 'eshop_mellat', 'mellat payment', 'Trangell', '2016-00-00 00:00:00', 'Copyright 2016 Trangell Team', 'http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2', 'info@trangell.com', 'https://trangell.com/fa/', '0.0.1', 'This is Mellat payment for Eshop', NULL, 21, 0);
