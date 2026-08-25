-- Actualizar loterías colombianas con días y horarios correctos
-- Ejecutar en phpMyAdmin o cliente MySQL

TRUNCATE TABLE `lotteries`;

INSERT INTO `lotteries` (`name`, `day_of_week`, `draw_time`, `active`) VALUES
('Lotería de Cundinamarca', 'monday',    '22:30:00', 1),
('Lotería de Tolima',       'monday',    '23:00:00', 1),
('Lotería Cruz Roja',       'tuesday',   '22:30:00', 1),
('Lotería de Huila',        'tuesday',   '22:30:00', 1),
('Lotería de Manizales',    'wednesday', '22:30:00', 1),
('Lotería del Meta',        'wednesday', '22:30:00', 1),
('Lotería del Valle',       'wednesday', '22:30:00', 1),
('Lotería Quindío',         'thursday',  '22:30:00', 1),
('Lotería de Bogotá',       'thursday',  '22:30:00', 1),
('Lotería de Santander',    'friday',    '23:00:00', 1),
('Lotería de Medellín',     'friday',    '23:00:00', 1),
('Lotería Risaralda',       'friday',    '23:00:00', 1),
('Lotería de Boyacá',       'saturday',  '22:40:00', 1),
('Lotería de Cauca',        'saturday',  '21:40:00', 1),
('Extra de Colombia',       'saturday',  '23:00:00', 1);
