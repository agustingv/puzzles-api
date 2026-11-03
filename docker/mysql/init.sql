-- The tests run against their own database, which the application's user may use too.
CREATE DATABASE IF NOT EXISTS puzzles_test;
GRANT ALL PRIVILEGES ON puzzles_test.* TO 'puzzles'@'%';
