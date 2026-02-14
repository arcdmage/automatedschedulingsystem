ALTER TABLE time_slots 
ADD COLUMN section_id INT NULL,
ADD FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE;

CREATE INDEX idx_section_timeslots ON time_slots(section_id, slot_order);