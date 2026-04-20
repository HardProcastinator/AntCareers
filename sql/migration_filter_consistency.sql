-- ============================================================================
-- Migration: Filter Consistency — Normalize mismatched dropdown values
-- File: sql/migration_filter_consistency.sql
-- Run once against your antcareers database.
-- Country names match getCountries() in includes/countries.php exactly.
-- Industry names match $industryKeys in antcareers_seekerJobs.php exactly.
-- ============================================================================

-- ─── 1. Normalize seeker_profiles.experience_level ───────────────────────────
-- Old values → canonical: Entry | Junior | Mid | Senior | Lead | Executive

UPDATE seeker_profiles SET experience_level = 'Entry'     WHERE experience_level IN ('Entry Level','entry level','Entry level','ENTRY','entry');
UPDATE seeker_profiles SET experience_level = 'Junior'    WHERE experience_level IN ('Junior Level','junior level','Junior level','JUNIOR','junior');
UPDATE seeker_profiles SET experience_level = 'Mid'       WHERE experience_level IN ('Mid Level','mid level','Mid level','Mid-level','MIDDLE','Middle','MID','mid');
UPDATE seeker_profiles SET experience_level = 'Senior'    WHERE experience_level IN ('Senior Level','senior level','Senior level','SENIOR','senior');
UPDATE seeker_profiles SET experience_level = 'Lead'      WHERE experience_level IN ('Lead Level','lead level','Lead level','Lead / Manager','Lead/Manager','Lead Manager','LEAD','lead','Manager','manager');
UPDATE seeker_profiles SET experience_level = 'Executive' WHERE experience_level IN ('Executive Level','executive level','Executive level','EXECUTIVE','executive','C-Level','C-Suite');

-- ─── 2. Normalize jobs.industry ──────────────────────────────────────────────
-- Canonical list = the 30 browse-jobs industries from antcareers_seekerJobs.php

UPDATE jobs SET industry = 'Accounting'                         WHERE industry IN ('Accountancy','Accounts','Finance - Accounting','Accounting & Finance');
UPDATE jobs SET industry = 'Administration & Office Support'    WHERE industry IN ('Administration','Admin','Office Support','Admin & Office Support','Admin and Office Support');
UPDATE jobs SET industry = 'Advertising, Arts & Media'          WHERE industry IN ('Media & Creative','Media','Creative','Advertising','Arts & Media','Media and Creative','Arts, Media & Design');
UPDATE jobs SET industry = 'Banking & Financial Services'       WHERE industry IN ('Finance & Banking','Finance and Banking','Finance','Banking','Financial Services','Bank');
UPDATE jobs SET industry = 'Call Centre & Customer Service'     WHERE industry IN ('BPO / Outsourcing','BPO','Outsourcing','BPO/Outsourcing','Business Process Outsourcing','Customer Service','Call Centre','Call Center');
UPDATE jobs SET industry = 'CEO & General Management'           WHERE industry IN ('General Management','C-Suite','Executive Management','Senior Management');
UPDATE jobs SET industry = 'Community Services & Development'   WHERE industry IN ('Community Services','Social Services','NGO','Non-Profit','Nonprofit','Community Development');
UPDATE jobs SET industry = 'Construction'                        WHERE industry IN ('Building & Construction','Building Construction','Civil Construction');
UPDATE jobs SET industry = 'Consulting & Strategy'              WHERE industry IN ('Consulting','Strategy','Management Consulting','Business Strategy','Advisory');
UPDATE jobs SET industry = 'Design & Architecture'              WHERE industry IN ('Design','Architecture','Interior Design','Graphic Design','UX/UI','UI/UX');
UPDATE jobs SET industry = 'Education & Training'               WHERE industry IN ('Education','Training','Education and Training','Academia','Teaching','School');
UPDATE jobs SET industry = 'Engineering'                         WHERE industry IN ('Engineering Services','Civil Engineering','Mechanical Engineering','Electrical Engineering');
UPDATE jobs SET industry = 'Farming, Animals & Conservation'    WHERE industry IN ('Agriculture','Farming','Agri','Animal Care','Conservation','Environment');
UPDATE jobs SET industry = 'Government & Defence'               WHERE industry IN ('Government','Government & Defense','Government and Defence','Defence','Defense','Public Sector','Military');
UPDATE jobs SET industry = 'Healthcare & Medical'               WHERE industry IN ('Healthcare','Health Care','Medical','Health & Medical','Hospital','Pharma','Pharmaceutical');
UPDATE jobs SET industry = 'Hospitality & Tourism'              WHERE industry IN ('Hospitality','Tourism','Hotel','Travel','Travel & Tourism');
UPDATE jobs SET industry = 'Human Resources & Recruitment'      WHERE industry IN ('Human Resources','HR','Recruitment','HR & Recruitment','Talent Acquisition','People & Culture');
UPDATE jobs SET industry = 'Information & Communication Technology' WHERE industry IN ('Information Technology','IT','Technology','Tech','Information Technology (IT)','ICT','Software','Software Development','Web Development');
UPDATE jobs SET industry = 'Insurance & Superannuation'         WHERE industry IN ('Insurance','Superannuation','Insurance & Finance');
UPDATE jobs SET industry = 'Legal'                               WHERE industry IN ('Law','Legal Services','Law Firm');
UPDATE jobs SET industry = 'Manufacturing, Transport & Logistics' WHERE industry IN ('Manufacturing','Transport','Logistics','Manufacturing & Logistics','Transport & Logistics','Supply Chain','Warehousing');
UPDATE jobs SET industry = 'Marketing & Communications'         WHERE industry IN ('Marketing','Communications','PR','Public Relations','Digital Marketing','Marketing & PR');
UPDATE jobs SET industry = 'Mining, Resources & Energy'         WHERE industry IN ('Mining','Resources','Energy','Oil & Gas','Utilities');
UPDATE jobs SET industry = 'Real Estate & Property'             WHERE industry IN ('Real Estate','Property','Property Management','Property Development');
UPDATE jobs SET industry = 'Retail & Consumer Products'         WHERE industry IN ('E-Commerce / Retail','E-Commerce','Ecommerce','Retail','Consumer Products','E-Commerce/Retail','FMCG');
UPDATE jobs SET industry = 'Sales'                               WHERE industry IN ('Sales & Marketing','Sales Management','Business Development','BD');
UPDATE jobs SET industry = 'Science & Technology'               WHERE industry IN ('Science','Research','R&D','Research and Development','Biotechnology','Biotech');
UPDATE jobs SET industry = 'Self Employment'                     WHERE industry IN ('Self-Employed','Freelance','Freelancing','Entrepreneurship','Startup');
UPDATE jobs SET industry = 'Sports & Recreation'                WHERE industry IN ('Sports','Recreation','Fitness','Gym','Athletics');
UPDATE jobs SET industry = 'Trades & Services'                  WHERE industry IN ('Trades','Services','Skilled Trades','Blue Collar','Technical Services');

-- ─── 3. Normalize company_profiles.industry ──────────────────────────────────
-- Same canonical mappings applied to company profiles

UPDATE company_profiles SET industry = 'Accounting'                         WHERE industry IN ('Accountancy','Accounts','Finance - Accounting','Accounting & Finance');
UPDATE company_profiles SET industry = 'Administration & Office Support'    WHERE industry IN ('Administration','Admin','Office Support','Admin & Office Support','Admin and Office Support');
UPDATE company_profiles SET industry = 'Advertising, Arts & Media'          WHERE industry IN ('Media & Creative','Media','Creative','Advertising','Arts & Media','Media and Creative','Arts, Media & Design');
UPDATE company_profiles SET industry = 'Banking & Financial Services'       WHERE industry IN ('Finance & Banking','Finance and Banking','Finance','Banking','Financial Services','Bank');
UPDATE company_profiles SET industry = 'Call Centre & Customer Service'     WHERE industry IN ('BPO / Outsourcing','BPO','Outsourcing','BPO/Outsourcing','Business Process Outsourcing','Customer Service','Call Centre','Call Center');
UPDATE company_profiles SET industry = 'CEO & General Management'           WHERE industry IN ('General Management','C-Suite','Executive Management','Senior Management');
UPDATE company_profiles SET industry = 'Community Services & Development'   WHERE industry IN ('Community Services','Social Services','NGO','Non-Profit','Nonprofit','Community Development');
UPDATE company_profiles SET industry = 'Construction'                        WHERE industry IN ('Building & Construction','Building Construction','Civil Construction');
UPDATE company_profiles SET industry = 'Consulting & Strategy'              WHERE industry IN ('Consulting','Strategy','Management Consulting','Business Strategy','Advisory');
UPDATE company_profiles SET industry = 'Design & Architecture'              WHERE industry IN ('Design','Architecture','Interior Design','Graphic Design','UX/UI','UI/UX');
UPDATE company_profiles SET industry = 'Education & Training'               WHERE industry IN ('Education','Training','Education and Training','Academia','Teaching','School');
UPDATE company_profiles SET industry = 'Engineering'                         WHERE industry IN ('Engineering Services','Civil Engineering','Mechanical Engineering','Electrical Engineering');
UPDATE company_profiles SET industry = 'Farming, Animals & Conservation'    WHERE industry IN ('Agriculture','Farming','Agri','Animal Care','Conservation','Environment');
UPDATE company_profiles SET industry = 'Government & Defence'               WHERE industry IN ('Government','Government & Defense','Government and Defence','Defence','Defense','Public Sector','Military');
UPDATE company_profiles SET industry = 'Healthcare & Medical'               WHERE industry IN ('Healthcare','Health Care','Medical','Health & Medical','Hospital','Pharma','Pharmaceutical');
UPDATE company_profiles SET industry = 'Hospitality & Tourism'              WHERE industry IN ('Hospitality','Tourism','Hotel','Travel','Travel & Tourism');
UPDATE company_profiles SET industry = 'Human Resources & Recruitment'      WHERE industry IN ('Human Resources','HR','Recruitment','HR & Recruitment','Talent Acquisition','People & Culture');
UPDATE company_profiles SET industry = 'Information & Communication Technology' WHERE industry IN ('Information Technology','IT','Technology','Tech','Information Technology (IT)','ICT','Software','Software Development','Web Development');
UPDATE company_profiles SET industry = 'Insurance & Superannuation'         WHERE industry IN ('Insurance','Superannuation','Insurance & Finance');
UPDATE company_profiles SET industry = 'Legal'                               WHERE industry IN ('Law','Legal Services','Law Firm');
UPDATE company_profiles SET industry = 'Manufacturing, Transport & Logistics' WHERE industry IN ('Manufacturing','Transport','Logistics','Manufacturing & Logistics','Transport & Logistics','Supply Chain','Warehousing');
UPDATE company_profiles SET industry = 'Marketing & Communications'         WHERE industry IN ('Marketing','Communications','PR','Public Relations','Digital Marketing','Marketing & PR');
UPDATE company_profiles SET industry = 'Mining, Resources & Energy'         WHERE industry IN ('Mining','Resources','Energy','Oil & Gas','Utilities');
UPDATE company_profiles SET industry = 'Real Estate & Property'             WHERE industry IN ('Real Estate','Property','Property Management','Property Development');
UPDATE company_profiles SET industry = 'Retail & Consumer Products'         WHERE industry IN ('E-Commerce / Retail','E-Commerce','Ecommerce','Retail','Consumer Products','E-Commerce/Retail','FMCG');
UPDATE company_profiles SET industry = 'Sales'                               WHERE industry IN ('Sales & Marketing','Sales Management','Business Development','BD');
UPDATE company_profiles SET industry = 'Science & Technology'               WHERE industry IN ('Science','Research','R&D','Research and Development','Biotechnology','Biotech');
UPDATE company_profiles SET industry = 'Self Employment'                     WHERE industry IN ('Self-Employed','Freelance','Freelancing','Entrepreneurship','Startup');
UPDATE company_profiles SET industry = 'Sports & Recreation'                WHERE industry IN ('Sports','Recreation','Fitness','Gym','Athletics');
UPDATE company_profiles SET industry = 'Trades & Services'                  WHERE industry IN ('Trades','Services','Skilled Trades','Blue Collar','Technical Services');

-- ─── 4. Normalize company_profiles.country ───────────────────────────────────
-- Maps ISO-2 codes AND common alternate names → exact names from getCountries()
-- Source: includes/countries.php getCountries() — all 195 countries

UPDATE company_profiles SET country = 'Afghanistan'                 WHERE country IN ('AF','Afghanistan');
UPDATE company_profiles SET country = 'Albania'                     WHERE country IN ('AL','Albania');
UPDATE company_profiles SET country = 'Algeria'                     WHERE country IN ('DZ','Algeria');
UPDATE company_profiles SET country = 'Andorra'                     WHERE country IN ('AD','Andorra');
UPDATE company_profiles SET country = 'Angola'                      WHERE country IN ('AO','Angola');
UPDATE company_profiles SET country = 'Antigua and Barbuda'         WHERE country IN ('AG','Antigua and Barbuda','Antigua & Barbuda');
UPDATE company_profiles SET country = 'Argentina'                   WHERE country IN ('AR','Argentina');
UPDATE company_profiles SET country = 'Armenia'                     WHERE country IN ('AM','Armenia');
UPDATE company_profiles SET country = 'Australia'                   WHERE country IN ('AU','AUS','Australia');
UPDATE company_profiles SET country = 'Austria'                     WHERE country IN ('AT','Austria');
UPDATE company_profiles SET country = 'Azerbaijan'                  WHERE country IN ('AZ','Azerbaijan');
UPDATE company_profiles SET country = 'Bahamas'                     WHERE country IN ('BS','Bahamas','The Bahamas');
UPDATE company_profiles SET country = 'Bahrain'                     WHERE country IN ('BH','Bahrain');
UPDATE company_profiles SET country = 'Bangladesh'                  WHERE country IN ('BD','Bangladesh');
UPDATE company_profiles SET country = 'Barbados'                    WHERE country IN ('BB','Barbados');
UPDATE company_profiles SET country = 'Belarus'                     WHERE country IN ('BY','Belarus');
UPDATE company_profiles SET country = 'Belgium'                     WHERE country IN ('BE','Belgium');
UPDATE company_profiles SET country = 'Belize'                      WHERE country IN ('BZ','Belize');
UPDATE company_profiles SET country = 'Benin'                       WHERE country IN ('BJ','Benin');
UPDATE company_profiles SET country = 'Bhutan'                      WHERE country IN ('BT','Bhutan');
UPDATE company_profiles SET country = 'Bolivia'                     WHERE country IN ('BO','Bolivia');
UPDATE company_profiles SET country = 'Bosnia and Herzegovina'      WHERE country IN ('BA','Bosnia and Herzegovina','Bosnia & Herzegovina','Bosnia');
UPDATE company_profiles SET country = 'Botswana'                    WHERE country IN ('BW','Botswana');
UPDATE company_profiles SET country = 'Brazil'                      WHERE country IN ('BR','Brazil','Brasil');
UPDATE company_profiles SET country = 'Brunei'                      WHERE country IN ('BN','Brunei','Brunei Darussalam');
UPDATE company_profiles SET country = 'Bulgaria'                    WHERE country IN ('BG','Bulgaria');
UPDATE company_profiles SET country = 'Burkina Faso'                WHERE country IN ('BF','Burkina Faso');
UPDATE company_profiles SET country = 'Burundi'                     WHERE country IN ('BI','Burundi');
UPDATE company_profiles SET country = 'Cambodia'                    WHERE country IN ('KH','Cambodia');
UPDATE company_profiles SET country = 'Cameroon'                    WHERE country IN ('CM','Cameroon');
UPDATE company_profiles SET country = 'Canada'                      WHERE country IN ('CA','CAN','Canada');
UPDATE company_profiles SET country = 'Cape Verde'                  WHERE country IN ('CV','Cape Verde','Cabo Verde');
UPDATE company_profiles SET country = 'Central African Republic'    WHERE country IN ('CF','Central African Republic','CAR');
UPDATE company_profiles SET country = 'Chad'                        WHERE country IN ('TD','Chad');
UPDATE company_profiles SET country = 'Chile'                       WHERE country IN ('CL','Chile');
UPDATE company_profiles SET country = 'China'                       WHERE country IN ('CN','China','PRC','Peoples Republic of China');
UPDATE company_profiles SET country = 'Colombia'                    WHERE country IN ('CO','Colombia');
UPDATE company_profiles SET country = 'Comoros'                     WHERE country IN ('KM','Comoros');
UPDATE company_profiles SET country = 'Congo'                       WHERE country IN ('CG','Congo','Republic of Congo');
UPDATE company_profiles SET country = 'Costa Rica'                  WHERE country IN ('CR','Costa Rica');
UPDATE company_profiles SET country = 'Croatia'                     WHERE country IN ('HR','Croatia');
UPDATE company_profiles SET country = 'Cuba'                        WHERE country IN ('CU','Cuba');
UPDATE company_profiles SET country = 'Cyprus'                      WHERE country IN ('CY','Cyprus');
UPDATE company_profiles SET country = 'Czech Republic'              WHERE country IN ('CZ','Czech Republic','Czechia');
UPDATE company_profiles SET country = 'Denmark'                     WHERE country IN ('DK','Denmark');
UPDATE company_profiles SET country = 'Djibouti'                    WHERE country IN ('DJ','Djibouti');
UPDATE company_profiles SET country = 'Dominica'                    WHERE country IN ('DM','Dominica');
UPDATE company_profiles SET country = 'Dominican Republic'          WHERE country IN ('DO','Dominican Republic');
UPDATE company_profiles SET country = 'Ecuador'                     WHERE country IN ('EC','Ecuador');
UPDATE company_profiles SET country = 'Egypt'                       WHERE country IN ('EG','Egypt');
UPDATE company_profiles SET country = 'El Salvador'                 WHERE country IN ('SV','El Salvador');
UPDATE company_profiles SET country = 'Equatorial Guinea'           WHERE country IN ('GQ','Equatorial Guinea');
UPDATE company_profiles SET country = 'Eritrea'                     WHERE country IN ('ER','Eritrea');
UPDATE company_profiles SET country = 'Estonia'                     WHERE country IN ('EE','Estonia');
UPDATE company_profiles SET country = 'Ethiopia'                    WHERE country IN ('ET','Ethiopia');
UPDATE company_profiles SET country = 'Fiji'                        WHERE country IN ('FJ','Fiji');
UPDATE company_profiles SET country = 'Finland'                     WHERE country IN ('FI','Finland');
UPDATE company_profiles SET country = 'France'                      WHERE country IN ('FR','France');
UPDATE company_profiles SET country = 'Gabon'                       WHERE country IN ('GA','Gabon');
UPDATE company_profiles SET country = 'Gambia'                      WHERE country IN ('GM','Gambia','The Gambia');
UPDATE company_profiles SET country = 'Georgia'                     WHERE country IN ('GE','Georgia');
UPDATE company_profiles SET country = 'Germany'                     WHERE country IN ('DE','Germany','Deutschland');
UPDATE company_profiles SET country = 'Ghana'                       WHERE country IN ('GH','Ghana');
UPDATE company_profiles SET country = 'Greece'                      WHERE country IN ('GR','Greece');
UPDATE company_profiles SET country = 'Grenada'                     WHERE country IN ('GD','Grenada');
UPDATE company_profiles SET country = 'Guatemala'                   WHERE country IN ('GT','Guatemala');
UPDATE company_profiles SET country = 'Guinea'                      WHERE country IN ('GN','Guinea');
UPDATE company_profiles SET country = 'Guinea-Bissau'               WHERE country IN ('GW','Guinea-Bissau','Guinea Bissau');
UPDATE company_profiles SET country = 'Guyana'                      WHERE country IN ('GY','Guyana');
UPDATE company_profiles SET country = 'Haiti'                       WHERE country IN ('HT','Haiti');
UPDATE company_profiles SET country = 'Honduras'                    WHERE country IN ('HN','Honduras');
UPDATE company_profiles SET country = 'Hungary'                     WHERE country IN ('HU','Hungary');
UPDATE company_profiles SET country = 'Iceland'                     WHERE country IN ('IS','Iceland');
UPDATE company_profiles SET country = 'India'                       WHERE country IN ('IN','IND','India');
UPDATE company_profiles SET country = 'Indonesia'                   WHERE country IN ('ID','Indonesia');
UPDATE company_profiles SET country = 'Iran'                        WHERE country IN ('IR','Iran');
UPDATE company_profiles SET country = 'Iraq'                        WHERE country IN ('IQ','Iraq');
UPDATE company_profiles SET country = 'Ireland'                     WHERE country IN ('IE','Ireland','Republic of Ireland');
UPDATE company_profiles SET country = 'Israel'                      WHERE country IN ('IL','Israel');
UPDATE company_profiles SET country = 'Italy'                       WHERE country IN ('IT','Italy','Italia');
UPDATE company_profiles SET country = 'Jamaica'                     WHERE country IN ('JM','Jamaica');
UPDATE company_profiles SET country = 'Japan'                       WHERE country IN ('JP','JPN','Japan');
UPDATE company_profiles SET country = 'Jordan'                      WHERE country IN ('JO','Jordan');
UPDATE company_profiles SET country = 'Kazakhstan'                  WHERE country IN ('KZ','Kazakhstan');
UPDATE company_profiles SET country = 'Kenya'                       WHERE country IN ('KE','Kenya');
UPDATE company_profiles SET country = 'Kiribati'                    WHERE country IN ('KI','Kiribati');
UPDATE company_profiles SET country = 'Kuwait'                      WHERE country IN ('KW','Kuwait');
UPDATE company_profiles SET country = 'Kyrgyzstan'                  WHERE country IN ('KG','Kyrgyzstan');
UPDATE company_profiles SET country = 'Laos'                        WHERE country IN ('LA','Laos','Lao PDR');
UPDATE company_profiles SET country = 'Latvia'                      WHERE country IN ('LV','Latvia');
UPDATE company_profiles SET country = 'Lebanon'                     WHERE country IN ('LB','Lebanon');
UPDATE company_profiles SET country = 'Lesotho'                     WHERE country IN ('LS','Lesotho');
UPDATE company_profiles SET country = 'Liberia'                     WHERE country IN ('LR','Liberia');
UPDATE company_profiles SET country = 'Libya'                       WHERE country IN ('LY','Libya');
UPDATE company_profiles SET country = 'Liechtenstein'               WHERE country IN ('LI','Liechtenstein');
UPDATE company_profiles SET country = 'Lithuania'                   WHERE country IN ('LT','Lithuania');
UPDATE company_profiles SET country = 'Luxembourg'                  WHERE country IN ('LU','Luxembourg');
UPDATE company_profiles SET country = 'Madagascar'                  WHERE country IN ('MG','Madagascar');
UPDATE company_profiles SET country = 'Malawi'                      WHERE country IN ('MW','Malawi');
UPDATE company_profiles SET country = 'Malaysia'                    WHERE country IN ('MY','Malaysia');
UPDATE company_profiles SET country = 'Maldives'                    WHERE country IN ('MV','Maldives');
UPDATE company_profiles SET country = 'Mali'                        WHERE country IN ('ML','Mali');
UPDATE company_profiles SET country = 'Malta'                       WHERE country IN ('MT','Malta');
UPDATE company_profiles SET country = 'Marshall Islands'            WHERE country IN ('MH','Marshall Islands');
UPDATE company_profiles SET country = 'Mauritania'                  WHERE country IN ('MR','Mauritania');
UPDATE company_profiles SET country = 'Mauritius'                   WHERE country IN ('MU','Mauritius');
UPDATE company_profiles SET country = 'Mexico'                      WHERE country IN ('MX','Mexico','México');
UPDATE company_profiles SET country = 'Micronesia'                  WHERE country IN ('FM','Micronesia','Federated States of Micronesia');
UPDATE company_profiles SET country = 'Moldova'                     WHERE country IN ('MD','Moldova','Republic of Moldova');
UPDATE company_profiles SET country = 'Monaco'                      WHERE country IN ('MC','Monaco');
UPDATE company_profiles SET country = 'Mongolia'                    WHERE country IN ('MN','Mongolia');
UPDATE company_profiles SET country = 'Montenegro'                  WHERE country IN ('ME','Montenegro');
UPDATE company_profiles SET country = 'Morocco'                     WHERE country IN ('MA','Morocco');
UPDATE company_profiles SET country = 'Mozambique'                  WHERE country IN ('MZ','Mozambique');
UPDATE company_profiles SET country = 'Myanmar'                     WHERE country IN ('MM','Myanmar','Burma');
UPDATE company_profiles SET country = 'Namibia'                     WHERE country IN ('NA','Namibia');
UPDATE company_profiles SET country = 'Nauru'                       WHERE country IN ('NR','Nauru');
UPDATE company_profiles SET country = 'Nepal'                       WHERE country IN ('NP','Nepal');
UPDATE company_profiles SET country = 'Netherlands'                 WHERE country IN ('NL','Netherlands','Holland');
UPDATE company_profiles SET country = 'New Zealand'                 WHERE country IN ('NZ','New Zealand','NZ');
UPDATE company_profiles SET country = 'Nicaragua'                   WHERE country IN ('NI','Nicaragua');
UPDATE company_profiles SET country = 'Niger'                       WHERE country IN ('NE','Niger');
UPDATE company_profiles SET country = 'Nigeria'                     WHERE country IN ('NG','Nigeria');
UPDATE company_profiles SET country = 'North Korea'                 WHERE country IN ('KP','North Korea','DPRK');
UPDATE company_profiles SET country = 'North Macedonia'             WHERE country IN ('MK','North Macedonia','Macedonia');
UPDATE company_profiles SET country = 'Norway'                      WHERE country IN ('NO','Norway');
UPDATE company_profiles SET country = 'Oman'                        WHERE country IN ('OM','Oman');
UPDATE company_profiles SET country = 'Pakistan'                    WHERE country IN ('PK','Pakistan');
UPDATE company_profiles SET country = 'Palau'                       WHERE country IN ('PW','Palau');
UPDATE company_profiles SET country = 'Palestine'                   WHERE country IN ('PS','Palestine','Palestinian Territory');
UPDATE company_profiles SET country = 'Panama'                      WHERE country IN ('PA','Panama');
UPDATE company_profiles SET country = 'Papua New Guinea'            WHERE country IN ('PG','Papua New Guinea','PNG');
UPDATE company_profiles SET country = 'Paraguay'                    WHERE country IN ('PY','Paraguay');
UPDATE company_profiles SET country = 'Peru'                        WHERE country IN ('PE','Peru');
UPDATE company_profiles SET country = 'Philippines'                 WHERE country IN ('PH','Philippines','Republic of the Philippines','Phil','Pilipinas');
UPDATE company_profiles SET country = 'Poland'                      WHERE country IN ('PL','Poland','Polska');
UPDATE company_profiles SET country = 'Portugal'                    WHERE country IN ('PT','Portugal');
UPDATE company_profiles SET country = 'Qatar'                       WHERE country IN ('QA','Qatar');
UPDATE company_profiles SET country = 'Romania'                     WHERE country IN ('RO','Romania');
UPDATE company_profiles SET country = 'Russia'                      WHERE country IN ('RU','Russia','Russian Federation');
UPDATE company_profiles SET country = 'Rwanda'                      WHERE country IN ('RW','Rwanda');
UPDATE company_profiles SET country = 'Saint Kitts and Nevis'       WHERE country IN ('KN','Saint Kitts and Nevis','St Kitts and Nevis','St. Kitts and Nevis');
UPDATE company_profiles SET country = 'Saint Lucia'                 WHERE country IN ('LC','Saint Lucia','St Lucia','St. Lucia');
UPDATE company_profiles SET country = 'Saint Vincent and the Grenadines' WHERE country IN ('VC','Saint Vincent and the Grenadines','St Vincent','St. Vincent');
UPDATE company_profiles SET country = 'Samoa'                       WHERE country IN ('WS','Samoa');
UPDATE company_profiles SET country = 'San Marino'                  WHERE country IN ('SM','San Marino');
UPDATE company_profiles SET country = 'Sao Tome and Principe'       WHERE country IN ('ST','Sao Tome and Principe','São Tomé and Príncipe');
UPDATE company_profiles SET country = 'Saudi Arabia'                WHERE country IN ('SA','Saudi Arabia','KSA');
UPDATE company_profiles SET country = 'Senegal'                     WHERE country IN ('SN','Senegal');
UPDATE company_profiles SET country = 'Serbia'                      WHERE country IN ('RS','Serbia');
UPDATE company_profiles SET country = 'Seychelles'                  WHERE country IN ('SC','Seychelles');
UPDATE company_profiles SET country = 'Sierra Leone'                WHERE country IN ('SL','Sierra Leone');
UPDATE company_profiles SET country = 'Singapore'                   WHERE country IN ('SG','Singapore','Republic of Singapore','SIN');
UPDATE company_profiles SET country = 'Slovakia'                    WHERE country IN ('SK','Slovakia');
UPDATE company_profiles SET country = 'Slovenia'                    WHERE country IN ('SI','Slovenia');
UPDATE company_profiles SET country = 'Solomon Islands'             WHERE country IN ('SB','Solomon Islands');
UPDATE company_profiles SET country = 'Somalia'                     WHERE country IN ('SO','Somalia');
UPDATE company_profiles SET country = 'South Africa'                WHERE country IN ('ZA','South Africa','RSA');
UPDATE company_profiles SET country = 'South Korea'                 WHERE country IN ('KR','South Korea','Korea','ROK');
UPDATE company_profiles SET country = 'South Sudan'                 WHERE country IN ('SS','South Sudan');
UPDATE company_profiles SET country = 'Spain'                       WHERE country IN ('ES','Spain','España');
UPDATE company_profiles SET country = 'Sri Lanka'                   WHERE country IN ('LK','Sri Lanka','Ceylon');
UPDATE company_profiles SET country = 'Sudan'                       WHERE country IN ('SD','Sudan');
UPDATE company_profiles SET country = 'Suriname'                    WHERE country IN ('SR','Suriname');
UPDATE company_profiles SET country = 'Sweden'                      WHERE country IN ('SE','Sweden');
UPDATE company_profiles SET country = 'Switzerland'                 WHERE country IN ('CH','Switzerland','Schweiz');
UPDATE company_profiles SET country = 'Syria'                       WHERE country IN ('SY','Syria');
UPDATE company_profiles SET country = 'Taiwan'                      WHERE country IN ('TW','Taiwan');
UPDATE company_profiles SET country = 'Tajikistan'                  WHERE country IN ('TJ','Tajikistan');
UPDATE company_profiles SET country = 'Tanzania'                    WHERE country IN ('TZ','Tanzania');
UPDATE company_profiles SET country = 'Thailand'                    WHERE country IN ('TH','Thailand');
UPDATE company_profiles SET country = 'Timor-Leste'                 WHERE country IN ('TL','Timor-Leste','East Timor');
UPDATE company_profiles SET country = 'Togo'                        WHERE country IN ('TG','Togo');
UPDATE company_profiles SET country = 'Tonga'                       WHERE country IN ('TO','Tonga');
UPDATE company_profiles SET country = 'Trinidad and Tobago'         WHERE country IN ('TT','Trinidad and Tobago','Trinidad & Tobago','Trinidad');
UPDATE company_profiles SET country = 'Tunisia'                     WHERE country IN ('TN','Tunisia');
UPDATE company_profiles SET country = 'Turkey'                      WHERE country IN ('TR','Turkey','Türkiye');
UPDATE company_profiles SET country = 'Turkmenistan'                WHERE country IN ('TM','Turkmenistan');
UPDATE company_profiles SET country = 'Tuvalu'                      WHERE country IN ('TV','Tuvalu');
UPDATE company_profiles SET country = 'Uganda'                      WHERE country IN ('UG','Uganda');
UPDATE company_profiles SET country = 'Ukraine'                     WHERE country IN ('UA','Ukraine');
UPDATE company_profiles SET country = 'United Arab Emirates'        WHERE country IN ('AE','United Arab Emirates','UAE','Emirati');
UPDATE company_profiles SET country = 'United Kingdom'              WHERE country IN ('GB','United Kingdom','UK','Great Britain','England','Scotland','Wales');
UPDATE company_profiles SET country = 'United States'               WHERE country IN ('US','USA','United States','United States of America','America');
UPDATE company_profiles SET country = 'Uruguay'                     WHERE country IN ('UY','Uruguay');
UPDATE company_profiles SET country = 'Uzbekistan'                  WHERE country IN ('UZ','Uzbekistan');
UPDATE company_profiles SET country = 'Vanuatu'                     WHERE country IN ('VU','Vanuatu');
UPDATE company_profiles SET country = 'Vatican City'                WHERE country IN ('VA','Vatican City','Holy See');
UPDATE company_profiles SET country = 'Venezuela'                   WHERE country IN ('VE','Venezuela');
UPDATE company_profiles SET country = 'Vietnam'                     WHERE country IN ('VN','Vietnam','Viet Nam');
UPDATE company_profiles SET country = 'Yemen'                       WHERE country IN ('YE','Yemen');
UPDATE company_profiles SET country = 'Zambia'                      WHERE country IN ('ZM','Zambia');
UPDATE company_profiles SET country = 'Zimbabwe'                    WHERE country IN ('ZW','Zimbabwe');

-- Clear unresolvable legacy value 'Other' (cannot map to a real country)
UPDATE company_profiles SET country = NULL WHERE country = 'Other';

-- ─── Done ─────────────────────────────────────────────────────────────────────
SELECT 'migration_filter_consistency.sql applied successfully' AS status;
