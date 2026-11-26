CREATE USER IF NOT EXISTS 'a2coffee_app'@'localhost'  IDENTIFIED BY 'GANTI_PASSWORD_ACAK';
CREATE USER IF NOT EXISTS 'a2coffee_app'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_ACAK';

GRANT SELECT, INSERT, UPDATE, DELETE ON pemweb_obe_proyeksemester.* TO 'a2coffee_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON pemweb_obe_proyeksemester.* TO 'a2coffee_app'@'127.0.0.1';
FLUSH PRIVILEGES;