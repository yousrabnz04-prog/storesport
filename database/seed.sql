-- Optional starter catalogue. Import after schema.sql:
--   mysql -u root -p storesport < database/seed.sql

INSERT INTO `categories` (`name`, `slug`, `description`, `icon`) VALUES
('Fitness', 'fitness', 'Weights, mats and everyday training gear.', 'fa-dumbbell'),
('Running', 'running', 'Shoes and accessories for road and trail.', 'fa-person-running'),
('Football', 'football', 'Balls, boots and team equipment.', 'fa-futbol'),
('Cycling', 'cycling', 'Helmets, lights and bike accessories.', 'fa-bicycle'),
('Swimming', 'swimming', 'Goggles, caps and training aids.', 'fa-person-swimming');

INSERT INTO `products` (`name`, `slug`, `category_id`, `description`, `short_description`, `price`, `compare_price`, `stock`, `featured`) VALUES
('Adjustable Dumbbell Set', 'adjustable-dumbbell-set', 1, 'A compact adjustable dumbbell pair that replaces a full rack of weights. Quick-lock dial, non-slip grip.', 'Adjustable pair, 2-24 kg each', 189.00, 229.00, 15, 1),
('Non-Slip Yoga Mat', 'non-slip-yoga-mat', 1, 'Extra-thick 6 mm mat with a textured surface for stability and joint comfort.', '6 mm thick, textured surface', 29.90, NULL, 40, 0),
('Trail Running Shoes', 'trail-running-shoes', 2, 'Lightweight trail shoes with an aggressive outsole and a breathable mesh upper.', 'Grippy and lightweight', 94.50, 119.00, 25, 1),
('Reflective Running Vest', 'reflective-running-vest', 2, 'High-visibility vest for early-morning and evening runs. Adjustable fit.', 'High-visibility, adjustable', 17.00, NULL, 60, 0),
('Match Football Size 5', 'match-football-size-5', 3, 'Thermally bonded match ball with a durable, water-resistant surface.', 'Size 5 match ball', 34.00, NULL, 50, 1),
('Shin Guards Pro', 'shin-guards-pro', 3, 'Lightweight shin guards with a hard shell and soft inner padding.', 'Hard shell, soft padding', 15.50, NULL, 70, 0),
('Road Cycling Helmet', 'road-cycling-helmet', 4, 'Ventilated, aerodynamic helmet with adjustable retention system.', 'Ventilated and aerodynamic', 79.00, 99.00, 18, 1),
('USB Rechargeable Bike Light Set', 'usb-bike-light-set', 4, 'Front and rear LED light set with several modes and USB charging.', 'Front and rear, USB charging', 24.90, NULL, 45, 0),
('Anti-Fog Swim Goggles', 'anti-fog-swim-goggles', 5, 'Comfortable goggles with UV protection and an anti-fog coating.', 'Anti-fog, UV protection', 14.90, NULL, 80, 0),
('Silicone Swim Cap', 'silicone-swim-cap', 5, 'Durable, stretchy silicone cap that stays put during training.', 'Durable silicone', 8.90, NULL, 90, 0);
