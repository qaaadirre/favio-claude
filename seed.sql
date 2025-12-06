-- seed.sql
-- Sample Data for Salon Management System

USE salon_management;

-- Insert Shops
INSERT INTO shops (name, address, phone, timezone) VALUES
('Elegant Salon - Downtown', '123 Main Street, Downtown, City', '9123456780', 'Asia/Kolkata'),
('Elegant Salon - Westside', '456 West Avenue, Westside, City', '9123456781', 'Asia/Kolkata'),
('Elegant Salon - Eastend', '789 East Boulevard, Eastend, City', '9123456782', 'Asia/Kolkata');

-- Insert Owner and Branch Admins
-- Password for all: password123
INSERT INTO users (shop_id, name, email, password_hash, role, phone) VALUES
(NULL, 'Owner Admin', 'owner@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', '9100000000'),
(1, 'Manager Downtown', 'manager1@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'branch_admin', '9100000001'),
(2, 'Manager Westside', 'manager2@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'branch_admin', '9100000002'),
(3, 'Manager Eastend', 'manager3@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'branch_admin', '9100000003'),
(1, 'Staff Member 1', 'staff1@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', '9100000004'),
(2, 'Staff Member 2', 'staff2@salon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', '9100000005');

-- Insert Employees for Branch 1 (Downtown)
INSERT INTO employees (shop_id, name, phone, age, role, monthly_salary, join_date) VALUES
(1, 'Rajesh Kumar', '9111111111', 28, 'Senior Stylist', 25000.00, '2023-01-15'),
(1, 'Priya Sharma', '9111111112', 25, 'Hair Stylist', 20000.00, '2023-03-20'),
(1, 'Amit Patel', '9111111113', 30, 'Barber', 22000.00, '2023-02-10'),
(1, 'Sneha Reddy', '9111111114', 24, 'Beauty Specialist', 18000.00, '2023-05-01'),
(1, 'Vikram Singh', '9111111115', 26, 'Assistant', 15000.00, '2023-06-15');

-- Insert Employees for Branch 2 (Westside)
INSERT INTO employees (shop_id, name, phone, age, role, monthly_salary, join_date) VALUES
(2, 'Deepak Verma', '9122222221', 29, 'Senior Stylist', 24000.00, '2023-01-20'),
(2, 'Anjali Mehta', '9122222222', 27, 'Hair Stylist', 21000.00, '2023-02-15'),
(2, 'Rahul Gupta', '9122222223', 31, 'Barber', 23000.00, '2023-03-10'),
(2, 'Kavita Joshi', '9122222224', 23, 'Beauty Specialist', 19000.00, '2023-04-05'),
(2, 'Suresh Yadav', '9122222225', 25, 'Assistant', 16000.00, '2023-05-20');

-- Insert Employees for Branch 3 (Eastend)
INSERT INTO employees (shop_id, name, phone, age, role, monthly_salary, join_date) VALUES
(3, 'Manoj Tiwari', '9133333331', 32, 'Senior Stylist', 26000.00, '2023-02-01'),
(3, 'Pooja Nair', '9133333332', 26, 'Hair Stylist', 20500.00, '2023-03-15'),
(3, 'Arun Kumar', '9133333333', 28, 'Barber', 22500.00, '2023-04-20'),
(3, 'Divya Singh', '9133333334', 24, 'Beauty Specialist', 18500.00, '2023-05-10'),
(3, 'Rohan Desai', '9133333335', 27, 'Assistant', 15500.00, '2023-06-01');

-- Insert Services
INSERT INTO services (shop_id, name, price, duration) VALUES
(1, 'Haircut - Men', 300.00, 30),
(1, 'Haircut - Women', 500.00, 45),
(1, 'Hair Color', 1500.00, 120),
(1, 'Facial', 800.00, 60),
(1, 'Shave', 200.00, 20),
(2, 'Haircut - Men', 300.00, 30),
(2, 'Haircut - Women', 500.00, 45),
(2, 'Hair Color', 1500.00, 120),
(2, 'Facial', 800.00, 60),
(2, 'Shave', 200.00, 20),
(3, 'Haircut - Men', 300.00, 30),
(3, 'Haircut - Women', 500.00, 45),
(3, 'Hair Color', 1500.00, 120),
(3, 'Facial', 800.00, 60),
(3, 'Shave', 200.00, 20);

-- Insert Sample Expenses for last 30 days
INSERT INTO expenses (shop_id, title, amount, date, time, category, description, created_by) VALUES
-- Branch 1
(1, 'Electricity Bill', 3500.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), '10:00:00', 'electricity', 'Monthly electricity bill', 2),
(1, 'Salon Products', 8000.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '11:30:00', 'materials', 'Shampoo, conditioner, colors', 2),
(1, 'Rent Payment', 15000.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), '09:00:00', 'rent', 'Monthly rent', 2),
(1, 'Staff Advance', 5000.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '14:00:00', 'employee_borrowed', 'Advance to Rajesh', 2),
(1, 'Cleaning Supplies', 1200.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:30:00', 'misc', 'Cleaning materials', 2),
(1, 'Equipment Repair', 2500.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '15:00:00', 'misc', 'Hair dryer repair', 2),
(1, 'Electricity Bill', 3200.00, CURDATE(), '10:00:00', 'electricity', 'Today electricity payment', 2),
-- Branch 2
(2, 'Electricity Bill', 3200.00, DATE_SUB(CURDATE(), INTERVAL 24 DAY), '10:00:00', 'electricity', 'Monthly electricity bill', 3),
(2, 'Salon Products', 7500.00, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '11:00:00', 'materials', 'Hair products stock', 3),
(2, 'Rent Payment', 14000.00, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00', 'rent', 'Monthly rent', 3),
(2, 'Staff Advance', 4000.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY), '13:00:00', 'employee_borrowed', 'Advance to Deepak', 3),
(2, 'Furniture Purchase', 12000.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '16:00:00', 'misc', 'New chairs', 3),
(2, 'Marketing Materials', 3000.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '11:00:00', 'misc', 'Flyers and banners', 3),
-- Branch 3
(3, 'Electricity Bill', 3400.00, DATE_SUB(CURDATE(), INTERVAL 26 DAY), '10:00:00', 'electricity', 'Monthly electricity bill', 4),
(3, 'Salon Products', 9000.00, DATE_SUB(CURDATE(), INTERVAL 19 DAY), '12:00:00', 'materials', 'Premium hair products', 4),
(3, 'Rent Payment', 16000.00, DATE_SUB(CURDATE(), INTERVAL 16 DAY), '09:00:00', 'rent', 'Monthly rent', 4),
(3, 'Staff Advance', 6000.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), '14:30:00', 'employee_borrowed', 'Advance to Manoj', 4),
(3, 'AC Maintenance', 4500.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '15:30:00', 'misc', 'AC servicing', 4),
(3, 'Office Supplies', 1500.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:00:00', 'misc', 'Stationery', 4);

-- Insert Deductions
INSERT INTO deductions (employee_id, shop_id, type, amount, date, note, created_by) VALUES
(1, 1, 'advance', 5000.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Salary advance', 2),
(6, 2, 'advance', 4000.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Salary advance', 3),
(11, 3, 'advance', 6000.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Salary advance', 4),
(2, 1, 'half_day', 333.33, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Half day leave', 2),
(7, 2, 'half_day', 350.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Half day leave', 3);

-- Insert Attendance (last 7 days)
INSERT INTO attendance (employee_id, shop_id, date, status, check_in_time, check_out_time) VALUES
-- Last 7 days for Branch 1 employees
(1, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'full_day', '09:00:00', '18:00:00'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'full_day', '09:05:00', '18:10:00'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'full_day', '08:55:00', '18:05:00'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'full_day', '09:00:00', '18:00:00'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'full_day', '09:10:00', '18:15:00'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'full_day', '09:00:00', '18:00:00'),
(1, 1, CURDATE(), 'full_day', '09:00:00', NULL),

(2, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'full_day', '09:00:00', '18:00:00'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'half_day', '09:00:00', '13:00:00'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'full_day', '09:00:00', '18:00:00'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'full_day', '09:00:00', '18:00:00'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'full_day', '09:00:00', '18:00:00'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'full_day', '09:00:00', '18:00:00'),
(2, 1, CURDATE(), 'full_day', '09:05:00', NULL);

-- Insert Tasks/Works Done (last 7 days)
INSERT INTO tasks (employee_id, shop_id, date, service_id, description, count, bonus_applicable) VALUES
-- Branch 1
(1, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1, 'Men haircuts', 8, 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1, 'Men haircuts', 10, 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 2, 'Women haircuts', 5, 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1, 'Men haircuts', 12, 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 3, 'Hair coloring', 3, 1),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 'Men haircuts', 9, 1),
(1, 1, CURDATE(), 1, 'Men haircuts', 6, 1),

(2, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 2, 'Women haircuts', 6, 1),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 2, 'Women haircuts', 4, 1),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 4, 'Facials', 5, 1),
(2, 1, CURDATE(), 2, 'Women haircuts', 7, 1);

-- Insert Shop Settings
INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES
(1, 'half_day_deduction_percent', '50'),
(2, 'half_day_deduction_percent', '50'),
(3, 'half_day_deduction_percent', '50'),
(1, 'bonus_per_task', '50'),
(2, 'bonus_per_task', '50'),
(3, 'bonus_per_task', '50');