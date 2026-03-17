DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_check_schedule_conflict`$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_schedule_conflict` (
    IN `p_faculty_id` INT,
    IN `p_section_id` INT,
    IN `p_schedule_date` DATE,
    IN `p_time_slot_id` INT,
    IN `p_room_id` INT,
    OUT `p_conflict_type` VARCHAR(50),
    OUT `p_conflict_message` VARCHAR(255)
)
BEGIN
    DECLARE teacher_conflict INT DEFAULT 0;
    DECLARE room_conflict INT DEFAULT 0;
    DECLARE section_conflict INT DEFAULT 0;
    DECLARE candidate_start TIME DEFAULT NULL;
    DECLARE candidate_end TIME DEFAULT NULL;

    -- Initialize output parameters
    SET p_conflict_type = NULL;
    SET p_conflict_message = NULL;

    -- Debugging: Log input parameters
    SELECT CONCAT('DEBUG SP: Inputs - FacultyID:', IFNULL(p_faculty_id, 'NULL'), ', SectionID:', IFNULL(p_section_id, 'NULL'), ', Date:', p_schedule_date, ', TimeSlotID:', p_time_slot_id, ', RoomID:', IFNULL(p_room_id, 'NULL')) AS debug_message;

    -- Capture candidate slot times (if available)
    IF p_time_slot_id IS NOT NULL AND p_time_slot_id != 0 THEN
        SELECT start_time, end_time INTO candidate_start, candidate_end
        FROM time_slots
        WHERE time_slot_id = p_time_slot_id
        LIMIT 1;
    END IF;

    -- Check teacher conflict
    IF p_faculty_id IS NOT NULL AND p_faculty_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO teacher_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.faculty_id = p_faculty_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO teacher_conflict
            FROM schedules
            WHERE faculty_id = p_faculty_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log teacher conflict check result
        SELECT CONCAT('DEBUG SP: Teacher Conflict Check - Count:', teacher_conflict) AS debug_message;

        IF teacher_conflict > 0 THEN
            SET p_conflict_type = 'Teacher Conflict';
            SET p_conflict_message = 'Teacher is already scheduled at this time';
        END IF;
    END IF;

    -- Check room conflict (only if p_room_id is provided)
    IF p_conflict_type IS NULL AND p_room_id IS NOT NULL AND p_room_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO room_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.room_id = p_room_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO room_conflict
            FROM schedules
            WHERE room_id = p_room_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log room conflict check result
        SELECT CONCAT('DEBUG SP: Room Conflict Check - Count:', room_conflict) AS debug_message;

        IF room_conflict > 0 THEN
            SET p_conflict_type = 'Room Conflict';
            SET p_conflict_message = 'Room is already scheduled at this time';
        END IF;
    END IF;

    -- Check section conflict
    -- This checks if *another* schedule exists for the *same* section at the *same* time slot.
    -- This might be redundant if the calling script guarantees unique assignments within a section,
    -- but it catches manually added duplicates or cross-generation issues.
    IF p_conflict_type IS NULL AND p_section_id IS NOT NULL AND p_section_id != 0 THEN
        IF candidate_start IS NOT NULL THEN
            SELECT COUNT(*) INTO section_conflict
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.time_slot_id
            WHERE s.section_id = p_section_id
              AND s.schedule_date = p_schedule_date
              AND NOT (ts.end_time <= candidate_start OR ts.start_time >= candidate_end);
        ELSE
            SELECT COUNT(*) INTO section_conflict
            FROM schedules
            WHERE section_id = p_section_id
              AND schedule_date = p_schedule_date
              AND time_slot_id = p_time_slot_id;
        END IF;

        -- Debugging: Log section conflict check result
        SELECT CONCAT('DEBUG SP: Section Conflict Check - Count:', section_conflict) AS debug_message;

        IF section_conflict > 0 THEN
            SET p_conflict_type = 'Section Conflict';
            SET p_conflict_message = 'Section already has a schedule at this time';
        END IF;
    END IF;

END$$

DELIMITER ;
