-- =====================================================================
-- AgriCart — Feedback half-star rating upgrade
-- The footer feedback widget (includes/footer.php) now lets visitors pick
-- half-star ratings (e.g. 4.5) and clear their rating entirely. This
-- migration widens `feedback.rating` from INT to DECIMAL(2,1) so those
-- values are stored precisely instead of being rounded to whole numbers.
-- Safe to run multiple times: only touches the column if the `feedback`
-- table and `rating` column actually exist on this database, and MODIFY
-- COLUMN is itself a no-op if the column is already DECIMAL(2,1).
-- =====================================================================

DELIMITER $$
DROP PROCEDURE IF EXISTS agri_widen_feedback_rating $$
CREATE PROCEDURE agri_widen_feedback_rating()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback' AND COLUMN_NAME = 'rating'
    ) THEN
        ALTER TABLE `feedback` MODIFY COLUMN `rating` DECIMAL(2,1) NULL DEFAULT NULL;
    END IF;
END $$
DELIMITER ;

CALL agri_widen_feedback_rating();
DROP PROCEDURE IF EXISTS agri_widen_feedback_rating;
