-- ============================================
-- DATOS DE PRUEBA - MisRifas
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Borrar en orden correcto (respetando foreign keys)
DELETE FROM raffle_winners;
DELETE FROM notifications;
DELETE FROM payments;
DELETE FROM commission_payments;
DELETE FROM tickets;
DELETE FROM raffles;
DELETE FROM lottery_results;
DELETE FROM users;
DELETE FROM system_settings;
DELETE FROM admin_users;
DELETE FROM lotteries;

-- ============================================
-- 1. LOTERÍAS
-- ============================================

INSERT INTO lotteries (id, name, day_of_week, draw_time, api_source, active) VALUES
(1, 'Lotería de Bogotá', 'saturday', '22:30:00', 'https://www.loteriasdeco.com/loteria-de-bogota', 1),
(2, 'Lotería de Boyacá', 'saturday', '22:30:00', 'https://www.loteriasdeco.com/loteria-de-boyaca', 1),
(3, 'Lotería de Medellín', 'friday', '22:00:00', 'https://www.loteriasdeco.com/loteria-de-medellin', 1),
(4, 'Lotería del Valle', 'saturday', '22:30:00', 'https://www.loteriasdeco.com/loteria-del-valle', 1),
(5, 'Lotería del Tolima', 'saturday', '22:30:00', 'https://www.loteriasdeco.com/loteria-del-tolima', 1),
(6, 'Lotería Cruz Roja', 'saturday', '22:30:00', NULL, 1);

-- ============================================
-- 2. ADMINISTRADORES
-- ============================================

INSERT INTO admin_users (id, username, email, password_hash, full_name, role, active, wompi_config) VALUES
(1, 'admin', 'admin@misrifas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', 'super_admin', 1, '{"public_key":"pub_prod_xxxxx","merchant_id":"123456"}'),
(2, 'creador1', 'creador1@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez', 'admin', 1, '{"public_key":"pub_prod_juan123","merchant_id":"789012"}'),
(3, 'creador2', 'creador2@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María García', 'admin', 1, '{"public_key":"pub_prod_maria456","merchant_id":"345678"}')
ON DUPLICATE KEY UPDATE username=VALUES(username), email=VALUES(email), full_name=VALUES(full_name);

-- ============================================
-- 3. USUARIOS (Compradores)
-- ============================================

INSERT INTO users (id, name, phone_whatsapp, email, unique_id, created_at) VALUES
(1, 'Carlos López', '3001234567', 'carlos@email.com', 'USR-0001-A1B2C3', '2026-01-15 10:30:00'),
(2, 'Ana María Torres', '3102345678', 'ana@email.com', 'USR-0002-D4E5F6', '2026-01-20 14:22:00'),
(3, 'Pedro Gómez', '3203456789', 'pedro@email.com', 'USR-0003-G7H8I9', '2026-02-01 09:15:00'),
(4, 'Laura Rodríguez', '3004567890', 'laura@email.com', 'USR-0004-J1K2L3', '2026-02-10 16:45:00'),
(5, 'Miguel Fernández', '3105678901', 'miguel@email.com', 'USR-0005-M4N5O6', '2026-02-15 11:20:00'),
(6, 'Sofia Castro', '3206789012', 'sofia@email.com', 'USR-0006-P7Q8R9', '2026-02-20 13:30:00'),
(7, 'Diego Martínez', '3007890123', 'diego@email.com', 'USR-0007-S1T2U3', '2026-03-01 08:45:00'),
(8, 'Carmen López', '3108901234', 'carmen@email.com', 'USR-0008-V4W5X6', '2026-03-05 15:10:00'),
(9, 'Javier Wilson', '3209012345', 'javier@email.com', 'USR-0009-Y7Z8A9', '2026-03-10 12:25:00'),
(10, 'Patricia Ruiz', '3001122334', 'patricia@email.com', 'USR-0010-B1C2D3', '2026-03-15 17:40:00');

-- ============================================
-- 4. RIFAS (20 rifas)
-- ============================================
-- Los registros ya fueron borrados con DELETE al inicio del archivo

INSERT INTO raffles (id, name, description, image_url, city, whatsapp_contact, responsible_person, ticket_price, total_tickets, digits, draw_date, lottery_id, opportunities, winning_mode, status, views, shares, commission_paid, commission_amount, commission_due_date, created_by, created_at) VALUES

-- Rifa 1: Carro 0km
(1, 'Carro 0km Toyota Corolla 2025', '¡Gánate un Toyota Corolla 2025 último modelo! El auto de tus sueños. Sorteo directo con lotería de Bogotá.', 'https://images.unsplash.com/photo-1621007947382-bb3c3994e2fb?w=800', 'Bogotá', '3001234567', 'Juan Pérez', 50000, 1000, 2, '2026-04-15 22:30:00', 1, 2, 'last_2', 'active', 1250, 89, 1, 500000, '2026-04-07 00:00:00', 2, '2026-01-10 08:00:00'),

-- Rifa 2: Moto BMW
(2, 'Moto BMW G 310 R', 'Moto BMW G 310 R Sport. La aventura te espera. ¡No dejes pasar esta oportunidad!', 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?w=800', 'Medellín', '3102345678', 'María García', 35000, 1000, 2, '2026-04-18 22:00:00', 3, 2, 'last_2', 'active', 980, 67, 1, 350000, '2026-04-10 00:00:00', 3, '2026-01-15 09:30:00'),

-- Rifa 3: Casa en Cartagena
(3, 'Casa Vacacional en Cartagena', 'Hermosa casa en el Rodadero, Santa Marta. 3 habitaciones, piscina, vista al mar. ¡Tu lugar de vacaciones perfecto!', 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800', 'Barranquilla', '3203456789', 'Juan Pérez', 100000, 1000, 3, '2026-04-20 22:30:00', 4, 3, 'last_3', 'active', 2100, 156, 1, 1000000, '2026-04-12 00:00:00', 2, '2026-01-20 10:15:00'),

-- Rifa 4: iPhone 16 Pro Max
(4, 'iPhone 16 Pro Max 1TB', 'El teléfono más esperado del año. iPhone 16 Pro Max con 1TB de almacenamiento. Color titanio natural.', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800', 'Cali', '3004567890', 'María García', 25000, 1000, 2, '2026-04-12 22:00:00', 2, 1, 'last_2', 'active', 1850, 203, 1, 250000, '2026-04-04 00:00:00', 3, '2026-02-01 14:20:00'),

-- Rifa 5: Jeep Wrangler
(5, 'Jeep Wrangler Unlimited Sahara', 'Jeep Wrangler 4x4 automático. La leyenda todoterreno. Preparado para cualquier aventura.', 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800', 'Bogotá', '3105678901', 'Juan Pérez', 75000, 1000, 3, '2026-05-01 22:30:00', 1, 2, 'last_3', 'active', 720, 45, 1, 750000, '2026-04-23 00:00:00', 2, '2026-02-05 11:45:00'),

-- Rifa 6: Viaje a Cancún
(6, 'Viaje Todo Incluido a Cancún 7 días', 'Paquete VIP: 7 días todo incluido para 2 personas en hotel 5 estrellas. Vuelo ida y vuelta incluido.', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800', 'Medellín', '3206789012', 'María García', 40000, 1000, 2, '2026-04-25 22:00:00', 3, 1, 'last_2', 'active', 650, 78, 1, 400000, '2026-04-17 00:00:00', 3, '2026-02-10 08:30:00'),

-- Rifa 7: Camioneta Toyota Hilux
(7, 'Toyota Hilux 4x4 Diesel', 'Toyota Hilux robusta y potente. Perfecta para trabajo y aventura. Doble cabina.', 'https://images.unsplash.com/photo-1601362840469-51e4d8d58785?w=800', 'Cali', '3007890123', 'Juan Pérez', 80000, 1000, 3, '2026-05-05 22:30:00', 2, 2, 'last_3', 'active', 480, 32, 1, 800000, '2026-04-27 00:00:00', 2, '2026-02-15 13:00:00'),

-- Rifa 8: Laptop MacBook Pro
(8, 'MacBook Pro 16" M4 Max', 'MacBook Pro 16 pulgadas con chip M4 Max. 36GB RAM, 1TB SSD. Para los profesionales más exigentes.', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800', 'Bogotá', '3108901234', 'María García', 30000, 1000, 2, '2026-04-10 22:30:00', 1, 1, 'last_2', 'active', 920, 112, 1, 300000, '2026-04-02 00:00:00', 3, '2026-02-20 10:20:00'),

-- Rifa 9: Apartamento en Bogotá
(9, 'Apartamento en Chico Norte', 'Apartamento de 120m² en el barrio Chico Norte. 3 habitaciones, 2 baños, 2 parques. Remodelado.', 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800', 'Bogotá', '3209012345', 'Juan Pérez', 150000, 1000, 3, '2026-05-10 22:30:00', 1, 2, 'last_3', 'active', 310, 28, 1, 1500000, '2026-05-02 00:00:00', 2, '2026-02-25 15:45:00'),

-- Rifa 10: Motocicleta Yamaha
(10, 'Yamaha MT-07 Dominator', 'Yamaha MT-07. naked bike de ensueño. 689cc, 73cv. Estilo y potencia en uno.', 'https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?w=800', 'Barranquilla', '3001122334', 'María García', 45000, 1000, 2, '2026-04-22 22:30:00', 4, 2, 'last_2', 'active', 540, 56, 1, 450000, '2026-04-14 00:00:00', 3, '2026-03-01 09:10:00'),

-- Rifa 11: Televisor 85 pulgadas
(11, 'Samsung Smart TV 85" 8K QLED', 'Samsung QLED 8K de 85 pulgadas. La experiencia cinematográfica definitiva en tu sala.', 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'Medellín', '3101234567', 'Juan Pérez', 20000, 1000, 2, '2026-04-08 22:00:00', 3, 1, 'last_2', 'active', 420, 48, 1, 200000, '2026-03-31 00:00:00', 2, '2026-03-05 12:30:00'),

-- Rifa 12: Fiesta de grado
(12, 'Fiesta de Grado Todo Incluido', 'Celebra tu graduación con una fiesta VIP para 50 personas. Music, comida, bebida y更多.', 'https://images.unsplash.com/photo-1530103862676-de3c9a59af38?w=800', 'Cali', '3202345678', 'María García', 15000, 1000, 2, '2026-04-30 22:30:00', 2, 1, 'last_2', 'active', 280, 35, 1, 150000, '2026-04-22 00:00:00', 3, '2026-03-10 14:00:00'),

-- Rifa 13: Refrigerador Premium
(13, 'Refrigerador Samsung Bespoke 4 Puertas', 'Samsung Bespoke con sistema de refrigeración Inteligente. 600L. Color personalizable.', 'https://images.unsplash.com/photo-1571175443880-49e1d25b2bc5?w=800', 'Bogotá', '3003456789', 'Juan Pérez', 25000, 1000, 2, '2026-04-14 22:30:00', 1, 1, 'last_2', 'active', 380, 41, 1, 250000, '2026-04-06 00:00:00', 2, '2026-03-15 08:20:00'),

-- Rifa 14: Viaje a Disney
(14, 'Viaje a Disney World Orlando 5 días', 'Paquete mágico para toda la familia. 5 días en Disney World, hotel 4 estrellas,ticket 4 parques.', 'https://images.unsplash.com/photo-1536697246787-1f7ae568d89a?w=800', 'Barranquilla', '3104567890', 'María García', 80000, 1000, 3, '2026-05-15 22:30:00', 4, 3, 'last_3', 'active', 190, 22, 1, 800000, '2026-05-07 00:00:00', 3, '2026-03-20 11:15:00'),

-- Rifa 15: PS5 Pro + Juegos
(15, 'PlayStation 5 Pro + 10 Juegos', 'PS5 Pro Bundle con 10 juegos digitales. Incluye mando adicional y auricular Pulse Elite.', 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?w=800', 'Medellín', '3205678901', 'Juan Pérez', 20000, 1000, 2, '2026-04-06 22:00:00', 3, 1, 'last_2', 'active', 760, 98, 1, 200000, '2026-03-29 00:00:00', 2, '2026-03-25 16:30:00'),

-- Rifa 16: Caballo FINO
(16, 'Caballo PRE Finca San Francisco', 'Hermoso caballo PRE (Pura Raza Española) de 5 años. Domado, ideal para competencia.', 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?w=800', 'Cali', '3006789012', 'María García', 100000, 1000, 3, '2026-05-20 22:30:00', 2, 2, 'last_3', 'active', 150, 18, 1, 1000000, '2026-05-12 00:00:00', 3, '2026-03-28 09:45:00'),

-- Rifa 17: Lavadora Secadora
(17, 'LG Signature WashTower', 'LG WashTower inteligente. Lavadora y secadora en una torre. 21kg capacidad. Tecnología AI.', 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800', 'Bogotá', '3107890123', 'Juan Pérez', 30000, 1000, 2, '2026-04-16 22:30:00', 1, 1, 'last_2', 'active', 290, 33, 1, 300000, '2026-04-08 00:00:00', 2, '2026-04-01 13:20:00'),

-- Rifa 18: Bodega en Bogotá
(18, 'Bodega Industrial en Fontibón', 'Bodega de 500m² en zona industrial de Fontibón. Oficina, baño, portón grande. Excelente ubicación.', 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=800', 'Bogotá', '3208901234', 'María García', 200000, 1000, 3, '2026-05-25 22:30:00', 1, 2, 'last_3', 'active', 85, 12, 1, 2000000, '2026-05-17 00:00:00', 3, '2026-04-05 10:00:00'),

-- Rifa 19: Parrilla Professional
(19, 'Parrilla Weber Genesis II 4 quemadores', 'Weber Genesis II. Parrilla a gas profesional. 4 quemadores,Side Burner,termómetro.', 'https://images.unsplash.com/photo-1529193591184-b1d58069ecdd?w=800', 'Barranquilla', '3009012345', 'Juan Pérez', 15000, 1000, 2, '2026-04-11 22:30:00', 4, 1, 'last_2', 'active', 340, 44, 1, 150000, '2026-04-03 00:00:00', 2, '2026-04-10 15:40:00'),

-- Rifa 20: Joyas de Oro
(20, 'Joya de Oro 18k - Set Completo', 'Set de joyas en oro 18k: collar, pulsera, aretes y anillo. Diseño exclusivo colombiano.', 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800', 'Cali', '3101122334', 'María García', 35000, 1000, 2, '2026-04-28 22:30:00', 2, 2, 'last_2', 'active', 410, 52, 1, 350000, '2026-04-20 00:00:00', 3, '2026-04-15 11:30:00');

-- ============================================
-- 5. BOLETOS (mezcla de vendidos, reservados y disponibles)
-- ============================================

-- Para cada rifa, generar distribución de boletos
-- Rifa 1: Carro (1000 boletos, ~60% vendidos)
INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_until, created_at) VALUES
(1, '00', '["00","99"]', 'paid', 1, NULL, '2026-01-12 10:00:00'),
(1, '01', '["01","98"]', 'paid', 2, NULL, '2026-01-12 11:00:00'),
(1, '02', '["02","97"]', 'paid', 3, NULL, '2026-01-13 09:00:00'),
(1, '03', '["03","96"]', 'paid', 4, NULL, '2026-01-14 14:00:00'),
(1, '04', '["04","95"]', 'paid', 5, NULL, '2026-01-15 10:00:00'),
(1, '05', '["05","94"]', 'paid', 6, NULL, '2026-01-16 11:00:00'),
(1, '06', '["06","93"]', 'paid', 7, NULL, '2026-01-17 15:00:00'),
(1, '07', '["07","92"]', 'paid', 8, NULL, '2026-01-18 09:00:00'),
(1, '08', '["08","91"]', 'reserved', NULL, '2026-04-05 18:00:00', '2026-04-03 18:00:00'),
(1, '09', '["09","90"]', 'reserved', NULL, '2026-04-05 19:00:00', '2026-04-03 19:00:00'),
(1, '10', '["10","89"]', 'paid', 9, NULL, '2026-01-20 10:00:00'),
(1, '11', '["11","88"]', 'paid', 10, NULL, '2026-01-21 11:00:00'),
(1, '12', '["12","87"]', 'paid', 1, NULL, '2026-01-22 14:00:00'),
(1, '13', '["13","86"]', 'reserved', NULL, '2026-04-05 20:00:00', '2026-04-03 20:00:00'),
(1, '14', '["14","85"]', 'paid', 2, NULL, '2026-01-24 10:00:00'),
(1, '15', '["15","84"]', 'paid', 3, NULL, '2026-01-25 11:00:00'),
(1, '16', '["16","83"]', 'paid', 4, NULL, '2026-01-26 15:00:00'),
(1, '17', '["17","82"]', 'available', NULL, NULL, '2026-01-27 09:00:00'),
(1, '18', '["18","81"]', 'available', NULL, NULL, '2026-01-28 10:00:00'),
(1, '19', '["19","80"]', 'available', NULL, NULL, '2026-01-29 11:00:00'),
(1, '20', '["20","79"]', 'available', NULL, NULL, '2026-01-30 14:00:00');

-- Rifa 2: Moto BMW
INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_until, created_at) VALUES
(2, '00', '["00","99"]', 'paid', 1, NULL, '2026-01-20 10:00:00'),
(2, '01', '["01","98"]', 'paid', 2, NULL, '2026-01-21 11:00:00'),
(2, '02', '["02","97"]', 'paid', 3, NULL, '2026-01-22 09:00:00'),
(2, '03', '["03","96"]', 'paid', 4, NULL, '2026-01-23 14:00:00'),
(2, '04', '["04","95"]', 'paid', 5, NULL, '2026-01-24 10:00:00'),
(2, '05', '["05","94"]', 'reserved', NULL, '2026-04-06 18:00:00', '2026-04-04 18:00:00'),
(2, '06', '["06","93"]', 'reserved', NULL, '2026-04-06 19:00:00', '2026-04-04 19:00:00'),
(2, '07', '["07","92"]', 'available', NULL, NULL, '2026-01-27 10:00:00'),
(2, '08', '["08","91"]', 'available', NULL, NULL, '2026-01-28 11:00:00'),
(2, '09', '["09","90"]', 'available', NULL, NULL, '2026-01-29 14:00:00'),
(2, '10', '["10","89"]', 'available', NULL, NULL, '2026-01-30 10:00:00');

-- Rifa 3: Casa Cartagena (muchos vendidos)
INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_until, created_at) VALUES
(3, '000', '["000","999","00","99"]', 'paid', 1, NULL, '2026-01-25 10:00:00'),
(3, '001', '["001","998","01","98"]', 'paid', 2, NULL, '2026-01-26 11:00:00'),
(3, '002', '["002","997","02","97"]', 'paid', 3, NULL, '2026-01-27 09:00:00'),
(3, '003', '["003","996","03","96"]', 'paid', 4, NULL, '2026-01-28 14:00:00'),
(3, '004', '["004","995","04","95"]', 'paid', 5, NULL, '2026-01-29 10:00:00'),
(3, '005', '["005","994","05","94"]', 'paid', 6, NULL, '2026-01-30 11:00:00'),
(3, '006', '["006","993","06","93"]', 'paid', 7, NULL, '2026-01-31 15:00:00'),
(3, '007', '["007","992","07","92"]', 'paid', 8, NULL, '2026-02-01 09:00:00'),
(3, '008', '["008","991","08","91"]', 'paid', 9, NULL, '2026-02-02 10:00:00'),
(3, '009', '["009","990","09","90"]', 'reserved', NULL, '2026-04-06 20:00:00', '2026-04-04 20:00:00'),
(3, '010', '["010","989","10","89"]', 'available', NULL, NULL, '2026-02-05 10:00:00'),
(3, '011', '["011","988","11","88"]', 'available', NULL, NULL, '2026-02-06 11:00:00');

-- Rifa 4: iPhone (muchos reservados)
INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_until, created_at) VALUES
(4, '00', '["00","99"]', 'paid', 1, NULL, '2026-02-10 10:00:00'),
(4, '01', '["01","98"]', 'paid', 2, NULL, '2026-02-11 11:00:00'),
(4, '02', '["02","97"]', 'reserved', NULL, '2026-04-04 18:00:00', '2026-04-02 18:00:00'),
(4, '03', '["03","96"]', 'reserved', NULL, '2026-04-04 19:00:00', '2026-04-02 19:00:00'),
(4, '04', '["04","95"]', 'reserved', NULL, '2026-04-04 20:00:00', '2026-04-02 20:00:00'),
(4, '05', '["05","94"]', 'reserved', NULL, '2026-04-05 10:00:00', '2026-04-03 10:00:00'),
(4, '06', '["06","93"]', 'available', NULL, NULL, '2026-02-15 10:00:00'),
(4, '07', '["07","92"]', 'available', NULL, NULL, '2026-02-16 11:00:00'),
(4, '08', '["08","91"]', 'available', NULL, NULL, '2026-02-17 14:00:00'),
(4, '09', '["09","90"]', 'available', NULL, NULL, '2026-02-18 10:00:00');

-- Rifa 5-20: Algunos ejemplos
INSERT INTO tickets (raffle_id, ticket_number, opportunities, status, user_id, reserved_until, created_at) VALUES
(5, '00', '["000","999","00","99"]', 'paid', 1, NULL, '2026-02-20 10:00:00'),
(5, '01', '["001","998","01","98"]', 'paid', 2, NULL, '2026-02-21 11:00:00'),
(5, '02', '["002","997","02","97"]', 'reserved', NULL, '2026-04-10 18:00:00', '2026-04-08 18:00:00'),
(5, '03', '["003","996","03","96"]', 'available', NULL, NULL, '2026-02-23 10:00:00'),

(6, '00', '["00","99"]', 'paid', 1, NULL, '2026-02-25 10:00:00'),
(6, '01', '["01","98"]', 'paid', 2, NULL, '2026-02-26 11:00:00'),
(6, '02', '["02","97"]', 'available', NULL, NULL, '2026-02-27 14:00:00'),

(7, '000', '["000","999","00","99"]', 'paid', 3, NULL, '2026-03-01 10:00:00'),
(7, '001', '["001","998","01","98"]', 'paid', 4, NULL, '2026-03-02 11:00:00'),
(7, '002', '["002","997","02","97"]', 'reserved', NULL, '2026-04-12 19:00:00', '2026-04-10 19:00:00'),
(7, '003', '["003","996","03","96"]', 'available', NULL, NULL, '2026-03-05 10:00:00'),

(8, '00', '["00","99"]', 'paid', 5, NULL, '2026-03-10 10:00:00'),
(8, '01', '["01","98"]', 'paid', 6, NULL, '2026-03-11 11:00:00'),
(8, '02', '["02","97"]', 'available', NULL, NULL, '2026-03-12 14:00:00'),

(9, '000', '["000","999","00","99"]', 'paid', 7, NULL, '2026-03-15 10:00:00'),
(9, '001', '["001","998","01","98"]', 'reserved', NULL, '2026-04-15 18:00:00', '2026-04-13 18:00:00'),
(9, '002', '["002","997","02","97"]', 'available', NULL, NULL, '2026-03-17 11:00:00'),

(10, '00', '["00","99"]', 'paid', 8, NULL, '2026-03-20 10:00:00'),
(10, '01', '["01","98"]', 'paid', 9, NULL, '2026-03-21 11:00:00'),
(10, '02', '["02","97"]', 'reserved', NULL, '2026-04-08 20:00:00', '2026-04-06 20:00:00'),
(10, '03', '["03","96"]', 'available', NULL, NULL, '2026-03-23 14:00:00'),

(11, '00', '["00","99"]', 'paid', 10, NULL, '2026-03-25 10:00:00'),
(11, '01', '["01","98"]', 'reserved', NULL, '2026-04-02 18:00:00', '2026-03-31 18:00:00'),
(11, '02', '["02","97"]', 'available', NULL, NULL, '2026-03-27 11:00:00'),

(12, '00', '["00","99"]', 'paid', 1, NULL, '2026-03-28 10:00:00'),
(12, '01', '["01","98"]', 'paid', 2, NULL, '2026-03-29 11:00:00'),
(12, '02', '["02","97"]', 'available', NULL, NULL, '2026-03-30 14:00:00'),

(13, '00', '["00","99"]', 'paid', 3, NULL, '2026-04-01 10:00:00'),
(13, '01', '["01","98"]', 'paid', 4, NULL, '2026-04-02 11:00:00'),
(13, '02', '["02","97"]', 'reserved', NULL, '2026-04-04 19:00:00', '2026-04-02 19:00:00'),
(13, '03', '["03","96"]', 'available', NULL, NULL, '2026-04-04 14:00:00'),

(14, '000', '["000","999","00","99"]', 'paid', 5, NULL, '2026-04-05 10:00:00'),
(14, '001', '["001","998","01","98"]', 'reserved', NULL, '2026-04-20 18:00:00', '2026-04-18 18:00:00'),
(14, '002', '["002","997","02","97"]', 'available', NULL, NULL, '2026-04-07 11:00:00'),

(15, '00', '["00","99"]', 'paid', 6, NULL, '2026-04-08 10:00:00'),
(15, '01', '["01","98"]', 'paid', 7, NULL, '2026-04-09 11:00:00'),
(15, '02', '["02","97"]', 'reserved', NULL, '2026-04-03 20:00:00', '2026-04-01 20:00:00'),
(15, '03', '["03","96"]', 'available', NULL, NULL, '2026-04-11 14:00:00'),

(16, '000', '["000","999","00","99"]', 'paid', 8, NULL, '2026-04-12 10:00:00'),
(16, '001', '["001","998","01","98"]', 'available', NULL, NULL, '2026-04-13 11:00:00'),

(17, '00', '["00","99"]', 'paid', 9, NULL, '2026-04-14 10:00:00'),
(17, '01', '["01","98"]', 'reserved', NULL, '2026-04-05 18:00:00', '2026-04-03 18:00:00'),
(17, '02', '["02","97"]', 'available', NULL, NULL, '2026-04-16 11:00:00'),

(18, '000', '["000","999","00","99"]', 'paid', 10, NULL, '2026-04-17 10:00:00'),
(18, '001', '["001","998","01","98"]', 'paid', 1, NULL, '2026-04-18 11:00:00'),
(18, '002', '["002","997","02","97"]', 'available', NULL, NULL, '2026-04-19 14:00:00'),

(19, '00', '["00","99"]', 'paid', 2, NULL, '2026-04-20 10:00:00'),
(19, '01', '["01","98"]', 'paid', 3, NULL, '2026-04-21 11:00:00'),
(19, '02', '["02","97"]', 'reserved', NULL, '2026-04-04 20:00:00', '2026-04-02 20:00:00'),
(19, '03', '["03","96"]', 'available', NULL, NULL, '2026-04-23 14:00:00'),

(20, '00', '["00","99"]', 'paid', 4, NULL, '2026-04-24 10:00:00'),
(20, '01', '["01","98"]', 'paid', 5, NULL, '2026-04-25 11:00:00'),
(20, '02', '["02","97"]', 'reserved', NULL, '2026-04-08 18:00:00', '2026-04-06 18:00:00'),
(20, '03', '["03","96"]', 'available', NULL, NULL, '2026-04-27 14:00:00');

-- ============================================
-- 6. RESULTADOS DE LOTERÍAS (para pruebas)
-- ============================================
INSERT INTO lottery_results (lottery_id, draw_date, winning_number, source_url) VALUES
(1, '2026-04-05', '45', 'https://www.loteriasdeco.com/loteria-de-bogota'),
(2, '2026-04-05', '78', 'https://www.loteriasdeco.com/loteria-de-boyaca'),
(3, '2026-04-04', '32', 'https://www.loteriasdeco.com/loteria-de-medellin'),
(4, '2026-04-05', '91', 'https://www.loteriasdeco.com/loteria-del-valle'),
(5, '2026-04-05', '56', 'https://www.loteriasdeco.com/loteria-del-tolima'),
(1, '2026-04-12', '23', 'https://www.loteriasdeco.com/loteria-de-bogota'),
(2, '2026-04-12', '67', 'https://www.loteriasdeco.com/loteria-de-boyaca'),
(3, '2026-04-11', '89', 'https://www.loteriasdeco.com/loteria-de-medellin'),
(4, '2026-04-12', '15', 'https://www.loteriasdeco.com/loteria-del-valle'),
(5, '2026-04-12', '44', 'https://www.loteriasdeco.com/loteria-del-tolima');

-- ============================================
-- 7. CONFIGURACIÓN DEL SISTEMA
-- ============================================
-- Los registros ya fueron borrados con DELETE al inicio del archivo

INSERT INTO system_settings (setting_key, setting_value, data_type, description) VALUES
('commission_enabled', '0', 'boolean', 'Comisión activada para rifas'),
('commission_percentage', '1', 'decimal', 'Porcentaje de comisión'),
('platform_name', 'MisRifas', 'string', 'Nombre de la plataforma'),
('platform_email', 'contacto@misrifas.com', 'string', 'Email de contacto'),
('min_ticket_price', '1000', 'integer', 'Precio mínimo por boleto'),
('max_ticket_price', '1000000', 'integer', 'Precio máximo por boleto'),
('reservation_hours', '2', 'integer', 'Horas de reserva por defecto'),
('max_tickets_per_user', '10', 'integer', 'Máximo boletos por compra');

-- ============================================
-- 8. NOTIFICACIONES (cola de ejemplo)
-- ============================================

INSERT INTO notifications (user_id, raffle_id, type, channel, recipient, subject, message, status, created_at) VALUES
(1, 1, 'winner', 'whatsapp', '3001234567', 'Resultado de Rifa', 'Hola Carlos! El resultado de la lotería de Bogotá fue el 45. Tu boleto 00 coincided!', 'pending', '2026-04-05 22:30:00'),
(2, 1, 'winner', 'email', 'ana@email.com', 'Resultado de Rifa', 'Hola Ana! El resultado de la lotería fue el 45. Tu boleto 01 coincided!', 'pending', '2026-04-05 22:30:00'),
(3, 1, 'winner', 'whatsapp', '3203456789', 'Resultado de Rifa', 'Hola Pedro! El resultado fue el 45. Tu boleto 02 coincided!', 'pending', '2026-04-12 22:30:00');

-- ============================================
-- RESUMEN DE DATOS
-- ============================================
-- 6 loterías
-- 3 administradores
-- 10 usuarios compradores
-- 20 rifas (todas activas)
-- ~120 tickets con mezcla de estados
-- 10 resultados de loterías
-- Configuración del sistema
-- 3 notificaciones pendientes

-- Re-habilitar check de foreign keys
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
