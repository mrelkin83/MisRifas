-- v4.17 — Bogotá como Distrito Capital
-- Bogotá no es un municipio de Cundinamarca: es entidad territorial propia
-- (Bogotá D.C.). El catálogo colombia.json ya la lista aparte; aquí se
-- corrigen los registros existentes que la tenían bajo Cundinamarca.
UPDATE raffles SET department = 'Bogotá D.C.' WHERE city = 'Bogotá' AND department = 'Cundinamarca';
UPDATE vendors SET department = 'Bogotá D.C.' WHERE city = 'Bogotá' AND department = 'Cundinamarca';
UPDATE users   SET department = 'Bogotá D.C.' WHERE city = 'Bogotá' AND department = 'Cundinamarca';
