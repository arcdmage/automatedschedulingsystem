-- auto_generate_helpers.sql
-- Helper SQL for schedule auto-generation tasks
-- IMPORTANT: Backup your database before running any of these statements.
-- Use phpMyAdmin (Import tab) or the SQL tab to run the statements below.
--
-- This file contains:
-- 1) A safe DELETE example to remove auto-generated schedule rows for a specific section.
-- 2) A stored-procedure template for sp_check_schedule_conflict (template only).
--
-- NOTES:
-- - Replace placeholder values (e.g., :SECTION_ID) with actual values before running.
-- - In phpMyAdmin paste single statements into the SQL tab (do not include PHP-style escaping).
-- - For routines/procedures you may use the Routines tab or the SQL tab with DELIMITER support.
-- - Do NOT run the procedure creation unless you intend to create/replace the procedure.
-- -------------------------------------------------------------

/* =========================================================================
   1) Delete auto-generated schedules for a specific section
   Usage:
     - Option A (single-statement): replace 3 with your section_id and run in SQL tab:
         DELETE FROM schedules
         WHERE section_id = 3
           AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%');

     - Option B (parameterized in session): run the two statements below in sequence:
         SET @section_id = 3;
         DELETE FROM schedules
         WHERE section_id = @section_id
           AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%');

   Important: Verify the WHERE clause before executing. Consider running a SELECT first:
         SELECT * FROM schedules
         WHERE section_id = 3
           AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%');
   ========================================================================= */

-- Example single-statement (replace 3 with actual section_id)
-- DELETE FROM schedules WHERE section_id = 3 AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%');

-- Example two-statement approach:
-- SET @section_id = 3;
-- DELETE FROM schedules
-- WHERE section_id = @section_id
--   AND (notes = 'Auto-generated' OR notes LIKE 'Auto-generated|%');



/* =========================================================================
   2) Stored procedure template: sp_check_schedule_conflict
   PURPOSE:
     - This is a template to help (re)create a stored procedure named
       sp_check_schedule_conflict with IN parameters and OUT parameters
       for conflict reporting. Replace the body with your real conflict checks.

   WARNING:
     - Do NOT run this template blindly if you already have a production
       stored procedure with the same name. Use DROP PROCEDURE IF EXISTS
       only if you are certain you want to replace it.
   ========================================================================= */

-- If you intend to create/replace the procedure, uncomment the DROP and CREATE blocks,
-- adjust the logic, then execute in phpMyAdmin's SQL tab (ensure DELIMITER lines are handled by your phpMyAdmin).
--
-- Example (uncomment to use):

-- DROP PROCEDURE IF EXISTS sp_check_schedule_conflict;
-- DELIMITER $$
-- CREATE PROCEDURE sp_check_schedule_conflict(
--     IN p_faculty_id INT,
--     IN p_section_id INT,
--     IN p_schedule_date DATE,
--     IN p_time_slot_id INT,
--     IN p_room_id INT,
--     OUT p_conflict_type VARCHAR(64),
--     OUT p_conflict_message TEXT
-- )
-- BEGIN
--     /*
--       Example placeholder logic:
--       1) Check if the section already has a schedule at that date/slot.
--       2) Check if the faculty is assigned elsewhere at that time.
--       3) Check room conflicts (if room_id is provided).
--       Set p_conflict_type and p_conflict_message accordingly, otherwise set them NULL.
--     */
--
--     DECLARE v_count INT DEFAULT 0;
--
--     -- Example: section conflict (ignore existing auto-generated rows if that's desired)
--     SELECT COUNT(*) INTO v_count
--     FROM schedules s
--     WHERE s.section_id = p_section_id
--       AND s.schedule_date = p_schedule_date
--       AND s.time_slot_id = p_time_slot_id
--     ;
--
--     IF v_count > 0 THEN
--         SET p_conflict_type = 'SectionConflict';
--         SET p_conflict_message = CONCAT('Section already has an entry on ', DATE_FORMAT(p_schedule_date, '%Y-%m-%d'), ' slot ', p_time_slot_id);
--         LEAVE proc_end; -- optional, you can check multiple conflict types instead of leaving early
--     END IF;
--
--     -- Example: faculty conflict
--     SELECT COUNT(*) INTO v_count
--     FROM schedules s
--     WHERE s.faculty_id = p_faculty_id
--       AND s.schedule_date = p_schedule_date
--       AND s.time_slot_id = p_time_slot_id
--     ;
--
--     IF v_count > 0 THEN
--         SET p_conflict_type = 'FacultyConflict';
--         SET p_conflict_message = CONCAT('Faculty ', p_faculty_id, ' has an overlapping schedule on ', DATE_FORMAT(p_schedule_date, '%Y-%m-%d'));
--         LEAVE proc_end;
--     END IF;
--
--     -- Example: room conflict (only check when p_room_id is not NULL)
--     IF p_room_id IS NOT NULL THEN
--         SELECT COUNT(*) INTO v_count
--         FROM schedules s
--         WHERE s.room_id = p_room_id
--           AND s.schedule_date = p_schedule_date
--           AND s.time_slot_id = p_time_slot_id
--         ;
--
--         IF v_count > 0 THEN
--             SET p_conflict_type = 'RoomConflict';
--             SET p_conflict_message = CONCAT('Room ', p_room_id, ' is already booked on ', DATE_FORMAT(p_schedule_date, '%Y-%m-%d'));
--             LEAVE proc_end;
--         END IF;
--     END IF;
--
--     -- If no conflicts found
--     SET p_conflict_type = NULL;
--     SET p_conflict_message = NULL;
--
-- proc_end: BEGIN
--     -- No-op label to allow LEAVE; end of procedure
-- END;
-- $$
-- DELIMITER ;

-- End of stored-procedure template
-- If you modify and create a real procedure, test it carefully:
--   CALL sp_check_schedule_conflict(NULL, 3, '2026-03-09', 14, NULL, @t, @m); SELECT @t AS conflict_type, @m AS conflict_message;

-- =========================================================
-- End of file
-- =========================================================
