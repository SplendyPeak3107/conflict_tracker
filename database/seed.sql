-- PeaceWatch starter data. Import AFTER schema.sql.

-- First admin account.
--   Email:    admin@peacewatch.local
--   Password: ChangeMe123!      <-- change it after your first login (My account page)
INSERT INTO users (name, email, phone, password_hash, role)
VALUES ('Administrator', 'admin@peacewatch.local', '0000000000', '$2b$10$W7R51.0/rJ.pVAYVd7IKd.nCPlkgvypaeT1I9ulpEhb1l25tPdila', 'admin');

-- Quick-reply templates for admin messages (editable in the admin "Quick replies" page)
INSERT INTO quick_replies (body, sort_order) VALUES
('We have received your report. An officer is reviewing it now.', 1),
('Help is on the way. Stay safe, and stay out of sight if you can.', 2),
('An officer is about 10 minutes away.', 3),
('Please keep away from the area if you can do so safely.', 4),
('Please post an update on what is happening now.', 5),
('The situation has been dealt with. Thank you for reporting.', 6);
