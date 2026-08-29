-- =====================================================
-- AgriCart — Fix: "Could not delete address, please try again."
-- =====================================================
-- Cause: orders.address_id has a foreign key to user_addresses(id)
-- with no ON DELETE rule, which defaults to RESTRICT. Once an address
-- has been used to place at least one order, MySQL refuses to delete
-- that address row, and the delete_address.php catch block turns that
-- into the generic "please try again" message.
--
-- Fix: change the FK to ON DELETE SET NULL. This is safe because
-- place_order.php now also saves the delivery name/mobile/address
-- directly onto the order itself (delivery_name/delivery_mobile/
-- delivery_address), so old orders keep showing the correct delivery
-- info even after the address book entry is deleted.
--
-- Run this once on your database.
-- =====================================================

ALTER TABLE `orders`
  DROP FOREIGN KEY `orders_ibfk_2`;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_2`
    FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`id`)
    ON DELETE SET NULL;
