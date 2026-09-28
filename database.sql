-- ==============================================================
-- East Systems Colleges of Rizal - Quiz App Database Schema
-- Database: quiz_app
-- Compatible with MySQL 5.7+ / MariaDB 10.4+ / phpMyAdmin
-- ==============================================================


DROP TABLE IF EXISTS `scores`;
DROP TABLE IF EXISTS `user_progress`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `grade` VARCHAR(10) NOT NULL DEFAULT '11',
  `category` VARCHAR(50) NOT NULL,
  `difficulty` ENUM('Easy', 'Average', 'Difficult', 'Mastery') NOT NULL DEFAULT 'Easy',
  `type` ENUM('multiple_choice', 'true_false', 'identification') NOT NULL DEFAULT 'multiple_choice',
  `question` TEXT NOT NULL,
  `choices` LONGTEXT NOT NULL,
  `correct` INT NOT NULL DEFAULT 0,
  `correct_text` VARCHAR(255) DEFAULT '',
  `explanation` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `student_name` VARCHAR(150) NOT NULL,
  `grade` VARCHAR(10) DEFAULT '11',
  `subject` VARCHAR(50) DEFAULT 'General',
  `difficulty` VARCHAR(30) DEFAULT 'All',
  `score` INT NOT NULL,
  `total_questions` INT NOT NULL,
  `percentage` INT NOT NULL,
  `points_earned` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`percentage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_progress` (
  `user_id` INT PRIMARY KEY,
  `points` INT DEFAULT 0,
  `level` INT DEFAULT 1,
  `lives` INT DEFAULT 5,
  `max_lives` INT DEFAULT 5,
  `streak` INT DEFAULT 0,
  `best_streak` INT DEFAULT 0,
  `total_qs` INT DEFAULT 0,
  `correct_qs` INT DEFAULT 0,
  `powerups_json` TEXT,
  `badges_json` TEXT,
  `subject_stats_json` TEXT,
  `daily_done_date` VARCHAR(50) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES
(1, 'Administrator', 'admin', 'admin123', 'admin'),
(2, 'Juan Dela Cruz', 'student', 'student123', 'student')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `password`=VALUES(`password`), `role`=VALUES(`role`);

INSERT INTO `questions` (`grade`, `category`, `difficulty`, `type`, `question`, `choices`, `correct`, `correct_text`, `explanation`) VALUES
('11', 'Science', 'Easy', 'multiple_choice', 'What is the primary chemical formula for Water?', '[\"H2O\",\"CO2\",\"O2\",\"NaCl\"]', 0, '', 'Water consists of 2 Hydrogen atoms bonded to 1 Oxygen atom (H2O).'),
('11', 'Science', 'Average', 'multiple_choice', 'Which cell organelle is known as the \"powerhouse of the cell\"?', '[\"Ribosome\",\"Mitochondria\",\"Endoplasmic Reticulum\",\"Golgi Apparatus\"]', 1, '', 'Mitochondria produce ATP through cellular respiration, supplying cellular energy.'),
('11', 'Science', 'Difficult', 'multiple_choice', 'Which type of rock is formed from the cooling and solidification of molten rock (magma or lava)?', '[\"Sedimentary\",\"Metamorphic\",\"Igneous\",\"Limestone\"]', 2, '', 'Igneous rocks (like basalt and granite) crystallize directly from molten magma or lava.'),
('11', 'Science', 'Mastery', 'multiple_choice', 'In plate tectonics, what landform is typically formed when two continental plates collide?', '[\"Mid-ocean ridge\",\"Mountain range\",\"Deep-sea trench\",\"Rift valley\"]', 1, '', 'Continental-continental convergence causes crustal crumpling that forms high mountain ranges like the Himalayas.'),
('12', 'Science', 'Easy', 'multiple_choice', 'Which planet in our solar system is known as the Red Planet?', '[\"Venus\",\"Mars\",\"Jupiter\",\"Mercury\"]', 1, '', 'Mars appears red due to iron oxide (rust) on its surface.'),
('12', 'Science', 'Average', 'multiple_choice', 'What type of chemical bond involves the transfer of electrons from a metal to a non-metal?', '[\"Covalent bond\",\"Ionic bond\",\"Metallic bond\",\"Hydrogen bond\"]', 1, '', 'An ionic bond is formed through electrostatic attraction after the complete transfer of valence electrons.'),
('12', 'Science', 'Difficult', 'multiple_choice', 'Which law states that energy cannot be created or destroyed, only transformed?', '[\"First Law of Thermodynamics\",\"Second Law of Thermodynamics\",\"Law of Conservation of Momentum\",\"Newton\'s Third Law\"]', 0, '', 'The First Law of Thermodynamics is the Law of Conservation of Energy.'),
('11', 'Science', 'Easy', 'true_false', 'Photosynthesis takes place primarily in the chloroplasts of plant cells.', '[\"True\",\"False\"]', 0, '', 'Chloroplasts contain chlorophyll which absorbs sunlight for photosynthesis.'),
('11', 'Science', 'Average', 'true_false', 'Sound travels faster in a vacuum than through air.', '[\"True\",\"False\"]', 1, '', 'Sound is a mechanical wave and requires a physical medium to travel; it cannot travel in a vacuum.'),
('12', 'Science', 'Difficult', 'true_false', 'DNA contains the nitrogenous base Uracil instead of Thymine.', '[\"True\",\"False\"]', 1, '', 'RNA contains Uracil; DNA contains Thymine.'),
('12', 'Science', 'Average', 'true_false', 'The ozone layer is primarily situated within the Stratosphere of Earth\'s atmosphere.', '[\"True\",\"False\"]', 0, '', 'The ozone layer resides in the stratosphere between 15 and 35 km above Earth.'),
('11', 'Science', 'Easy', 'identification', 'What is the chemical symbol for Gold in the periodic table?', '[]', 0, 'Au', 'Au comes from the Latin word \"Aurum\".'),
('11', 'Science', 'Average', 'identification', 'What organelle is known as the powerhouse of the cell?', '[]', 0, 'Mitochondria', 'Mitochondria produce cellular ATP energy.'),
('12', 'Science', 'Difficult', 'identification', 'What is the closest star to the Earth?', '[]', 0, 'Sun', 'The Sun is the star at the center of the Solar System.'),
('12', 'Science', 'Mastery', 'identification', 'What biological macromolecule carries the genetic instructions for all living organisms? (Acronym)', '[]', 0, 'DNA', 'DNA stands for Deoxyribonucleic Acid.'),
('11', 'Mathematics', 'Easy', 'multiple_choice', 'What is the product of 7 multiplied by 8?', '[\"54\",\"56\",\"49\",\"63\"]', 1, '', '7 × 8 = 56.'),
('11', 'Mathematics', 'Average', 'multiple_choice', 'If f(x) = 3x + 5, what is the value of f(4)?', '[\"12\",\"15\",\"17\",\"21\"]', 2, '', 'f(4) = 3(4) + 5 = 12 + 5 = 17.'),
('11', 'Mathematics', 'Difficult', 'multiple_choice', 'What is the simple interest on Php 10,000 invested at 5% annual rate for 3 years? (Formula: I = Prt)', '[\"Php 1,000\",\"Php 1,500\",\"Php 2,000\",\"Php 500\"]', 1, '', 'I = 10,000 × 0.05 × 3 = Php 1,500.'),
('11', 'Mathematics', 'Mastery', 'multiple_choice', 'Which of the following represents a rational inequality?', '[\"(2x - 1) \\/ (x + 3) > 0\",\"y = 2x^2 + 5x\",\"3x + 4 = 19\",\"f(x) = sqrt(x - 2)\"]', 0, '', 'A rational inequality involves rational expressions with inequality symbols (>, <, >=, <=).'),
('12', 'Mathematics', 'Easy', 'multiple_choice', 'In statistics, what measure of central tendency represents the most frequently occurring value?', '[\"Mean\",\"Median\",\"Mode\",\"Variance\"]', 2, '', 'The mode is the value with highest frequency.'),
('12', 'Mathematics', 'Average', 'multiple_choice', 'In a standard normal distribution (Z-distribution), what are the mean and standard deviation?', '[\"Mean = 1, SD = 0\",\"Mean = 0, SD = 1\",\"Mean = 50, SD = 10\",\"Mean = 100, SD = 15\"]', 1, '', 'A standard normal curve has a mean (μ) of 0 and a standard deviation (σ) of 1.'),
('12', 'Mathematics', 'Difficult', 'multiple_choice', 'How many unique 4-digit PIN codes can be formed from digits 0 to 9 if repetition is NOT allowed? (Formula: P(10,4))', '[\"5,040\",\"10,000\",\"2,520\",\"720\"]', 0, '', '10 × 9 × 8 × 7 = 5,040 permutations.'),
('11', 'Mathematics', 'Easy', 'true_false', 'The sum of all interior angles in any Euclidean triangle is always 180 degrees.', '[\"True\",\"False\"]', 0, '', 'The triangle angle sum theorem dictates that the 3 interior angles sum to 180°.'),
('11', 'Mathematics', 'Average', 'true_false', 'Zero is categorized as a positive integer.', '[\"True\",\"False\"]', 1, '', 'Zero is neutral: it is neither positive nor negative.'),
('12', 'Mathematics', 'Easy', 'true_false', 'The square root of 64 is equal to 8.', '[\"True\",\"False\"]', 0, '', '8 × 8 = 64.'),
('12', 'Mathematics', 'Difficult', 'true_false', 'A rational number cannot be represented as a ratio of two integers.', '[\"True\",\"False\"]', 1, '', 'By definition, rational numbers can be expressed as a fraction a/b where b is non-zero.'),
('11', 'Mathematics', 'Easy', 'identification', 'What is the value of Pi rounded to two decimal places?', '[]', 0, '3.14', 'Pi (π) is approximately 3.14159...'),
('11', 'Mathematics', 'Average', 'identification', 'In a right triangle, what famous theorem states that a^2 + b^2 = c^2?', '[]', 0, 'Pythagorean Theorem', 'Named after ancient Greek mathematician Pythagoras.'),
('12', 'Mathematics', 'Average', 'identification', 'What is the perimeter of a square with a side length of 5 cm?', '[]', 0, '20', 'Perimeter = 4 × side = 4 × 5 = 20 cm.'),
('12', 'Mathematics', 'Difficult', 'identification', 'What mathematical term describes a polynomial consisting of exactly two terms?', '[]', 0, 'Binomial', 'Bi- means two, as in binomial.'),
('11', 'English', 'Easy', 'multiple_choice', 'Which part of speech names a person, place, thing, or idea?', '[\"Verb\",\"Adjective\",\"Noun\",\"Adverb\"]', 2, '', 'A noun is a naming word for people, places, things, or concepts.'),
('11', 'English', 'Average', 'multiple_choice', 'Which figure of speech directly compares two unlike things using \"like\" or \"as\"?', '[\"Metaphor\",\"Simile\",\"Personification\",\"Hyperbole\"]', 1, '', 'Similes explicitly compare using words such as \"like\" or \"as\".'),
('11', 'English', 'Difficult', 'multiple_choice', 'Identify the sentence with correct subject-verb agreement:', '[\"Each of the students have submitted their project.\",\"The bouquet of red roses smell wonderful.\",\"Neither the teacher nor the students were absent yesterday.\",\"A number of books was donated to the library.\"]', 2, '', 'In \"neither... nor\", the verb agrees with the closer subject (\"students were\").'),
('11', 'English', 'Mastery', 'multiple_choice', 'In academic writing, what is the central argument or claim of an essay called?', '[\"Topic sentence\",\"Thesis statement\",\"Bibliography\",\"Hook\"]', 1, '', 'A thesis statement encapsulates the main claim and scope of an academic paper.'),
('12', 'English', 'Easy', 'multiple_choice', 'Which punctuation mark is used to indicate a direct quotation or title of a short work?', '[\"Parentheses\",\"Quotation marks\",\"Apostrophe\",\"Hyphen\"]', 1, '', 'Quotation marks (\" \") enclose direct speech or citations.'),
('12', 'English', 'Average', 'multiple_choice', 'What reading strategy involves quickly scanning a text to locate specific information?', '[\"Skimming\",\"Scanning\",\"Extensive reading\",\"In-depth analysis\"]', 1, '', 'Scanning is looking for specific keywords or facts without reading every word.'),
('12', 'English', 'Difficult', 'multiple_choice', 'What is the fallacious argument where one attacks the person\'s character rather than their argument?', '[\"Straw Man\",\"Ad Hominem\",\"Bandwagon\",\"False Dilemma\"]', 1, '', 'Ad hominem (Latin: \"to the person\") attacks character instead of the issue.'),
('11', 'English', 'Easy', 'true_false', 'The word \"quickly\" functions as an adverb in modern English grammar.', '[\"True\",\"False\"]', 0, '', '\"Quickly\" describes how an action is performed, modifying verbs.'),
('11', 'English', 'Average', 'true_false', 'An antonym is a word that has the identical meaning as another word.', '[\"True\",\"False\"]', 1, '', 'An antonym has opposite meaning; synonyms have similar meanings.'),
('12', 'English', 'Average', 'true_false', 'A thesis statement is usually positioned near the end of the introductory paragraph.', '[\"True\",\"False\"]', 0, '', 'Placing the thesis at the end of the introduction transitions effectively into body paragraphs.'),
('12', 'English', 'Difficult', 'true_false', 'Plagiarism is acceptable as long as you rewrite the passage in your own words without citation.', '[\"True\",\"False\"]', 1, '', 'Paraphrasing without attributing the original author is still plagiarism.'),
('11', 'English', 'Average', 'identification', 'What figure of speech gives human attributes or feelings to non-human objects?', '[]', 0, 'Personification', 'Example: \"The wind whispered through the trees.\"'),
('11', 'English', 'Easy', 'identification', 'What punctuation mark is used at the end of an asking or interrogative sentence?', '[]', 0, 'Question mark', 'The question mark (?) indicates an inquiry.'),
('12', 'English', 'Average', 'identification', 'What literary term refers to the main character or hero in a narrative story?', '[]', 0, 'Protagonist', 'The protagonist drives the main storyline.'),
('12', 'English', 'Easy', 'identification', 'What is the comparative degree of the adjective \"good\"?', '[]', 0, 'Better', 'Good, Better, Best.'),
('11', 'Filipino', 'Easy', 'multiple_choice', 'Ano ang pambansang wika ng Pilipinas ayon sa Saligang Batas ng 1987?', '[\"Tagalog\",\"Filipino\",\"Pilipino\",\"Ingles\"]', 1, '', 'Ayon sa Saligang Batas ng 1987, Artikulo XIV, Seksyon 6: Ang wikang pambansa ay Filipino.'),
('11', 'Filipino', 'Average', 'multiple_choice', 'Anong barayti ng wika ang tumutukoy sa pansariling paraan ng pagsasalita ng isang indibidwal?', '[\"Sosyolek\",\"Diyalekto\",\"Idyolek\",\"Etnolek\"]', 2, '', 'Ang idyolek ay ang indibidwal at natatanging estilo o tatak sa pagsasalita.'),
('11', 'Filipino', 'Difficult', 'multiple_choice', 'Anong gamit ng wika ayon kay M.A.K. Halliday ang ginagamit sa pagpapahayag ng sariling damdamin at opinyon?', '[\"Instrumental\",\"Personal\",\"Interaksiyonal\",\"Heuristiko\"]', 1, '', 'Ang personal na tungkulin ng wika ay nagpapahayag ng sariling pananaw at emosyon.'),
('11', 'Filipino', 'Mastery', 'multiple_choice', 'Ano ang tawag sa sinaunang sistema ng pagsulat ng mga katutubong Pilipino bago dumating ang mga Espanyol?', '[\"Abakada\",\"Baybayin\",\"Alibata\",\"Alpabetong Romano\"]', 1, '', 'Baybayin ang tamang tawag sa sinaunang katutubong paraan ng pagsulat ng mga Pilipino.'),
('12', 'Filipino', 'Easy', 'multiple_choice', 'Ano ang kahulugan ng salitang \"saliksik\" o \"pananaliksik\"?', '[\"Paghula\",\"Makaagham at sistematikong pagsusuri\",\"Kuwentong bayan\",\"Pangongopya\"]', 1, '', 'Ang pananaliksik ay isang sistematiko at makaagham na proseso ng pagtuklas ng impormasyon.'),
('12', 'Filipino', 'Average', 'multiple_choice', 'Aling bahagi ng pananaliksik ang naglalaman ng maikling buod ng buong pag-aaral?', '[\"Metodolohiya\",\"Abstrak\",\"Konklusyon\",\"Talasanggunian\"]', 1, '', 'Ang abstrak ay maikling buod ng pananaliksik.'),
('12', 'Filipino', 'Difficult', 'multiple_choice', 'Ano ang tawag sa ilegal na pagkopya o pag-angkin ng gawa, ideya, o salita ng iba nang walang wastong pagkilala?', '[\"Paraphrasing\",\"Plagiarism\",\"Sintesis\",\"Bibliyograpiya\"]', 1, '', 'Ang plagiarism ay paglabag sa karapatang-ari sa pamamagitan ng pag-angkin ng gawa ng iba nang walang credit.'),
('11', 'Filipino', 'Easy', 'true_false', 'Ang Tagalog, Pilipino, at Filipino ay magkakatulad at walang pagkakaiba sa kasaysayan.', '[\"True\",\"False\"]', 1, '', 'May pagkakaiba: ang Tagalog ay katutubong wika, Pilipino ang pambansang wika noong 1959, at Filipino noong 1987.'),
('11', 'Filipino', 'Average', 'true_false', 'Ang idyolek ay ang personal o natatanging estilo ng pagsasalita ng isang partikular na tao.', '[\"True\",\"False\"]', 0, '', 'Tama. Halimbawa ang kilalang estilo ni Mike Enriquez o Noli de Castro.'),
('12', 'Filipino', 'Easy', 'true_false', 'Ayon sa batas, ang pambansang wika ng Pilipinas ay Filipino.', '[\"True\",\"False\"]', 0, '', 'Tama, alinsunod sa 1987 Philippine Constitution Art. XIV Sec. 6.'),
('12', 'Filipino', 'Difficult', 'true_false', 'Ang talasanggunian o bibliyograpiya ay inilalagay sa pinakaunang pahina ng isang pamanahong papel.', '[\"True\",\"False\"]', 1, '', 'Mali. Ang talasanggunian ay inilalagay sa dulo ng pananaliksik.'),
('11', 'Filipino', 'Average', 'identification', 'Sino ang kinikilalang \"Ama ng Wikang Pambansa\" ng Pilipinas?', '[]', 0, 'Manuel L. Quezon', 'Si Pangulong Manuel L. Quezon ang nagsulong ng pagkakaroon ng pambansang wika.'),
('11', 'Filipino', 'Mastery', 'identification', 'Ano ang katutubong sistema ng pagsulat ng mga Pilipino bago dumating ang mga Espanyol?', '[]', 0, 'Baybayin', 'Baybayin ang sinaunang katutubong pagsulat.'),
('12', 'Filipino', 'Average', 'identification', 'Anong barayti ng wika ang nalilikha dahil sa heograpikal na dimensiyon o lokasyon?', '[]', 0, 'Diyalekto', 'Halimbawa: Tagalog-Batangas, Tagalog-Bulacan.'),
('12', 'Filipino', 'Easy', 'identification', 'Ano ang tawag sa maikling buod ng isang akademikong sulatin o pananaliksik?', '[]', 0, 'Abstrak', 'Ang abstrak ay maikling buod sa unahan ng pananaliksik.'),
('11', 'Social Science', 'Easy', 'multiple_choice', 'Sino ang pambansang bayani ng Pilipinas na sumulat ng \"Noli Me Tangere\" at \"El Filibusterismo\"?', '[\"Andres Bonifacio\",\"Dr. Jose Rizal\",\"Emilio Aguinaldo\",\"Apolinario Mabini\"]', 1, '', 'Isinulat ni Dr. Jose Rizal ang Noli Me Tangere at El Filibusterismo.'),
('11', 'Social Science', 'Average', 'multiple_choice', 'What sociological concept refers to the belief that one\'s own culture is superior to all other cultures?', '[\"Cultural Relativism\",\"Ethnocentrism\",\"Xenocentrism\",\"Subculture\"]', 1, '', 'Ethnocentrism is evaluating other cultures through standards of one\'s own culture.'),
('11', 'Social Science', 'Difficult', 'multiple_choice', 'Which branch of the Philippine government is constitutionally authorized to interpret and apply laws?', '[\"Executive Branch\",\"Legislative Branch\",\"Judicial Branch\",\"Civil Service Commission\"]', 2, '', 'The Judicial Branch (headed by the Supreme Court) holds the judicial power.'),
('11', 'Social Science', 'Mastery', 'multiple_choice', 'What is the term for the intangible aspects of culture including values, norms, beliefs, and language?', '[\"Material Culture\",\"Non-material Culture\",\"Archaeology\",\"Artifacts\"]', 1, '', 'Non-material culture consists of the ideas, attitudes, and beliefs of a society.'),
('12', 'Social Science', 'Easy', 'multiple_choice', 'How many years is one regular term of the President of the Philippines under the 1987 Constitution?', '[\"4 years\",\"5 years\",\"6 years\",\"7 years\"]', 2, '', 'Under Article VII of the 1987 Constitution, the President serves a single 6-year term.'),
('12', 'Social Science', 'Average', 'multiple_choice', 'What form of government is officially practiced in the Philippines?', '[\"Parliamentary Democracy\",\"Unitary Presidential Constitutional Republic\",\"Federal Monarchy\",\"Socialist State\"]', 1, '', 'The Philippines is governed as a unitary presidential constitutional republic.'),
('12', 'Social Science', 'Difficult', 'multiple_choice', 'What is the highest law of the land in the Republic of the Philippines?', '[\"Civil Code\",\"Revised Penal Code\",\"The 1987 Philippine Constitution\",\"Republic Act 10533\"]', 2, '', 'The Constitution is the fundamental and supreme law of the land.'),
('11', 'Social Science', 'Easy', 'true_false', 'The legislative branch of the Philippine government is composed of the Senate and the House of Representatives.', '[\"True\",\"False\"]', 0, '', 'The Congress of the Philippines is bicameral.'),
('11', 'Social Science', 'Average', 'true_false', 'Cultural relativism argues that cultures should be judged using the standards of Western societies.', '[\"True\",\"False\"]', 1, '', 'Cultural relativism states that a person\'s beliefs and practices should be understood based on that person\'s own culture.'),
('12', 'Social Science', 'Easy', 'true_false', 'Under Philippine law, an accused person is presumed innocent until proven guilty beyond reasonable doubt.', '[\"True\",\"False\"]', 0, '', 'Presumption of innocence is a constitutional right under the Bill of Rights.'),
('12', 'Social Science', 'Difficult', 'true_false', 'The 1987 Philippine Constitution was ratified during the administration of President Ferdinand Marcos Sr.', '[\"True\",\"False\"]', 1, '', 'It was ratified during the administration of President Corazon C. Aquino.'),
('11', 'Social Science', 'Easy', 'identification', 'Who is the national hero of the Philippines who wrote Noli Me Tangere?', '[]', 0, 'Jose Rizal', 'Dr. Jose Rizal.'),
('11', 'Social Science', 'Average', 'identification', 'What is the supreme and fundamental law of the land in the Philippines?', '[]', 0, 'Constitution', 'The 1987 Philippine Constitution.'),
('12', 'Social Science', 'Average', 'identification', 'What branch of the Philippine government is headed by the President and Vice President?', '[]', 0, 'Executive', 'The Executive branch implements and enforces laws.'),
('12', 'Social Science', 'Difficult', 'identification', 'What constitutional commission in the Philippines is tasked with administering elections? (Acronym)', '[]', 0, 'COMELEC', 'Commission on Elections (COMELEC).');
