-- Una categoria pot fer diverses voltes a diversos recorreguts (per exemple,
-- una volta al circuit A i dues al circuit B), en l'ordre que s'indiqui.
CREATE TABLE IF NOT EXISTS category_courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  laps INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_category_courses (category_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El recorregut que ja tenia cada categoria passa a ser el primer, amb una volta.
INSERT INTO category_courses (category_id, course_id, laps, sort_order)
SELECT id, course_id, 1, 0 FROM categories WHERE course_id IS NOT NULL;
