-- Sample data for Faculty Members, Subject List, and Sections List
-- 50 Faculty, 12 Subjects, 6 Sections (STEM x2, ABM x2, GAS x1, HUMMS x1)
-- Notes:
-- - "special" stores comma-separated LAST NAMES of faculty.
-- - school_year and semester are optional; using NULLs.

-- FACULTY (50 rows)
INSERT INTO faculty (fname, mname, lname, gender, pnumber, address, status) VALUES
('Aileen', 'M.', 'Santos', 'female', '09171234567', 'Bagong Sikat, San Jose', 'Married'),
('Mark', 'D.', 'Reyes', 'male', '09281234567', 'Poblacion, San Jose', 'Single'),
('Liza', 'Q.', 'Garcia', 'female', '09091230001', 'Labangan, San Jose', 'Single'),
('John', 'P.', 'Cruz', 'male', '09181230002', 'San Agustin, San Jose', 'Married'),
('Erika', 'N.', 'Lopez', 'female', '09211230003', 'Bubog, San Jose', 'Single'),
('Paolo', 'V.', 'Mendoza', 'male', '09331230004', 'Mangarin, San Jose', 'Single'),
('Joy', 'S.', 'Villanueva', 'female', '09161230005', 'Pag-asa, San Jose', ''),
('Ivan', 'T.', 'Ramos', 'male', '09271230006', 'Labangan, San Jose', 'Single'),
('Chloe', 'B.', 'Diaz', 'female', '09191230007', 'Bagong Sikat, San Jose', 'Single'),
('Noel', 'R.', 'Torres', 'male', '09081230008', 'Poblacion, San Jose', 'Married'),
('Cathy', 'L.', 'Navarro', 'female', '09172223344', 'Camburay, San Jose', 'Single'),
('Oscar', 'J.', 'Serrano', 'male', '09283334455', 'San Isidro, San Jose', 'Single'),
('Mona', 'E.', 'Domingo', 'female', '09394445566', 'Bubog, San Jose', ''),
('Gina', 'F.', 'Perez', 'female', '09175556677', 'Poblacion, San Jose', 'Single'),
('Allan', 'K.', 'Sison', 'male', '09286667788', 'Labo, San Jose', 'Married'),
('Nina', 'R.', 'Castillo', 'female', '09397778899', 'Mangarin, San Jose', 'Single'),
('Rodel', 'M.', 'Flores', 'male', '09178889900', 'Bagong Sikat, San Jose', ''),
('Jessa', 'P.', 'Alvarez', 'female', '09289990011', 'Pag-asa, San Jose', 'Single'),
('Marvin', 'C.', 'Aguilar', 'male', '09390001122', 'Labangan, San Jose', 'Married'),
('Diane', 'T.', 'Francisco', 'female', '09171112223', 'San Agustin, San Jose', ''),
('Leo', 'B.', 'Bautista', 'male', '09282223334', 'Poblacion, San Jose', 'Single'),
('Karla', 'V.', 'DelosReyes', 'female', '09393334445', 'Mangarin, San Jose', 'Single'),
('Arvin', 'S.', 'Santiago', 'male', '09174445556', 'Bubog, San Jose', 'Married'),
('Mira', 'G.', 'Padilla', 'female', '09285556667', 'Bagong Sikat, San Jose', ''),
('Julio', 'A.', 'Rizal', 'male', '09396667778', 'San Isidro, San Jose', 'Single'),
('Sheila', 'H.', 'Malonzo', 'female', '09177778889', 'Camburay, San Jose', 'Single'),
('Carlo', 'D.', 'Rosales', 'male', '09288889990', 'Labangan, San Jose', ''),
('Tessa', 'I.', 'Lim', 'female', '09399990001', 'Poblacion, San Jose', 'Married'),
('Ben', 'O.', 'Soriano', 'male', '09170001112', 'Pag-asa, San Jose', ''),
('Yna', 'W.', 'Vega', 'female', '09281112223', 'Bagong Sikat, San Jose', 'Single'),
('Randy', 'U.', 'Tolentino', 'male', '09392223334', 'San Agustin, San Jose', 'Single'),
('Hazel', 'D.', 'Ortega', 'female', '09173334445', 'Mangarin, San Jose', ''),
('Rhea', 'S.', 'Cortez', 'female', '09284445556', 'Poblacion, San Jose', 'Single'),
('Enzo', 'M.', 'Pascual', 'male', '09395556667', 'Bubog, San Jose', 'Single'),
('Liam', 'J.', 'Yu', 'male', '09176667778', 'Labangan, San Jose', ''),
('Faith', 'P.', 'Cabrera', 'female', '09287778889', 'Bagong Sikat, San Jose', 'Single'),
('Gio', 'K.', 'Marquez', 'male', '09398889990', 'San Isidro, San Jose', 'Married'),
('Lara', 'Q.', 'Aguirre', 'female', '09179990001', 'Poblacion, San Jose', 'Single'),
('Mika', 'L.', 'Tan', 'female', '09280001112', 'Mangarin, San Jose', ''),
('Noah', 'E.', 'Co', 'male', '09391112223', 'Bubog, San Jose', 'Single'),
('Bianca', 'Y.', 'Sanchez', 'female', '09172223345', 'Pag-asa, San Jose', 'Single'),
('Rico', 'N.', 'Villamor', 'male', '09283334456', 'Labangan, San Jose', ''),
('Kristel', 'I.', 'Abad', 'female', '09394445567', 'San Agustin, San Jose', 'Single'),
('Ian', 'C.', 'Gonzales', 'male', '09175556678', 'Poblacion, San Jose', 'Single'),
('Shane', 'E.', 'Salazar', 'female', '09286667789', 'Bagong Sikat, San Jose', 'Married'),
('Omar', 'F.', 'Lucero', 'male', '09397778890', 'Camburay, San Jose', ''),
('Pia', 'R.', 'Valdez', 'female', '09178889901', 'Mangarin, San Jose', 'Single'),
('Ken', 'S.', 'Batista', 'male', '09289990012', 'Bubog, San Jose', 'Single'),
('Zara', 'T.', 'Morales', 'female', '09390001123', 'Poblacion, San Jose', 'Single');

-- SUBJECTS (12 rows)
INSERT INTO subjects (subject_name, special, grade_level, strand) VALUES
('General Physics 1', 'Santos, Reyes', '12', ''),
('General Physics 2', 'Reyes, Mendoza', '12', ''),
('Entrepreneurship', 'Garcia, Cruz', '11', ''),
('Reading and Writing', 'Lopez, Villanueva', '11', ''),
('Practical Research 1', 'Diaz, Torres, Navarro', '11', ''),
('Practical Research 2', 'Torres, Perez', '12', ''),
('Media and Information Literacy', 'Ramos, Domingo', '11', ''),
('Applied Economics', 'Mendoza, Serrano', '12', ''),
('DRRM', 'Cruz, Santos', '11', ''),
('Physical Education', 'Diaz, Flores', '11', ''),
('Contemporary Philippine Arts', 'Lopez, Villanueva', '12', ''),
('Oral Communication', 'Garcia, Padilla', '11', '');

-- SECTIONS (6 rows)
INSERT INTO sections (section_name, grade_level, track, school_year, semester) VALUES
('STEM MARS', '12', 'STEM', NULL, NULL),
('STEM VENUS', '11', 'STEM', NULL, NULL),
('ABM SATURN', '11', 'ABM', NULL, NULL),
('ABM JUPITER', '12', 'ABM', NULL, NULL),
('GAS MERCURY', '11', 'GAS', NULL, NULL),
('HUMMS PLUTO', '12', 'HUMMS', NULL, NULL);
