SET NAMES latin1;

INSERT IGNORE INTO bio_turno_catalogo (codigo, nombre, hora_inicio, hora_fin, cruza_medianoche) VALUES
('DIA', 'Dia', '06:00:00', '18:00:00', 0),
('NOCHE', 'Noche', '18:00:00', '06:00:00', 1);

INSERT IGNORE INTO bio_punto_tipo (codigo, nombre) VALUES
('GARITA', 'Garita'),
('ALMACEN', 'Almacen'),
('CERCO', 'Cerco perimetral'),
('POZO', 'Pozo / agua'),
('BALANZA', 'Balenza'),
('OFICINA', 'Oficina bioseguridad');

INSERT IGNORE INTO bio_capacitacion (codigo, nombre, vigencia_meses, estado) VALUES
('BIO-01', 'Induccion bioseguridad', 12, 'ACTIVO'),
('BIO-02', 'Manejo de equipos EPPS', 12, 'ACTIVO'),
('BIO-03', 'Control acceso vehicular', 6, 'ACTIVO'),
('BIO-04', 'Primeros auxilios', 24, 'ACTIVO');

INSERT IGNORE INTO bio_config (clave, valor) VALUES
('descanso_min_horas', '8'),
('traslado_min_minutos_entre_granjas', '30'),
('capacitacion_catalogo_gestor', 'ambos'),
('capacitacion_registro_gestor', 'ambos');

SET @g1 = (SELECT TRIM(codgranja) FROM lk_granjas ORDER BY codgranja LIMIT 1);
SET @g2 = (SELECT TRIM(codgranja) FROM lk_granjas ORDER BY codgranja LIMIT 1 OFFSET 1);
SET @g1 = IFNULL(@g1, 'DEMO1');
SET @g2 = IFNULL(@g2, 'DEMO2');

INSERT INTO bio_puntos (granja_cod, codigo, nombre, tipo_cod, ubicacion, estado) VALUES
(@g1, 'GAR-01', 'Garita principal', 'GARITA', 'Acceso norte', 'ACTIVO'),
(@g1, 'ALM-01', 'Almacen central', 'ALMACEN', 'Modulo A', 'ACTIVO'),
(@g1, 'CER-01', 'Cerco este', 'CERCO', 'Lote 3', 'ACTIVO'),
(@g2, 'GAR-01', 'Garita principal', 'GARITA', 'Acceso sur', 'ACTIVO'),
(@g2, 'POZ-01', 'Pozo sector B', 'POZO', 'Sector B', 'ACTIVO'),
(@g2, 'BAL-01', 'Balenza carga', 'BALANZA', 'Muelle', 'ACTIVO');

INSERT INTO bio_punto_turno (punto_id, turno_cod, personal_requerido)
SELECT p.id, 'DIA', 1 FROM bio_puntos p;
INSERT INTO bio_punto_turno (punto_id, turno_cod, personal_requerido)
SELECT p.id, 'NOCHE', 1 FROM bio_puntos p WHERE p.tipo_cod IN ('GARITA', 'CERCO');
UPDATE bio_punto_turno t
JOIN bio_puntos p ON p.id = t.punto_id
SET t.personal_requerido = 2
WHERE p.codigo = 'GAR-01' AND t.turno_cod = 'DIA';

INSERT INTO bio_punto_capacitacion (punto_id, capacitacion_id)
SELECT p.id, c.id FROM bio_puntos p CROSS JOIN bio_capacitacion c WHERE c.codigo IN ('BIO-01','BIO-02');

INSERT INTO bio_personal (dni, nombres, apellidos, cargo, empresa, fecha_contratacion, estado, granja_base_cod) VALUES
('40111222', 'Carlos', 'Mendoza Ruiz', 'Vigilante', 'GRS', '2022-03-15', 'ACTIVO', @g1),
('40222333', 'Luis', 'Quispe Huaman', 'Vigilante', 'GRS', '2021-08-01', 'ACTIVO', @g1),
('40333444', 'Miguel', 'Torres Vega', 'Supervisor', 'GRS', '2020-01-10', 'ACTIVO', @g1),
('40444555', 'Jose', 'Ramos Paredes', 'Vigilante', 'TERCERO', '2023-06-20', 'ACTIVO', @g1),
('40555666', 'Pedro', 'Flores Diaz', 'Almacenero', 'GRS', '2019-11-05', 'ACTIVO', @g1),
('40666777', 'Ricardo', 'Soto Linares', 'Vigilante', 'GRS', '2022-09-12', 'ACTIVO', @g1),
('40777888', 'Fernando', 'Castro Gil', 'Operador cerco', 'TERCERO', '2024-02-01', 'ACTIVO', @g1),
('40888999', 'Alberto', 'Vargas Mejia', 'Vigilante', 'GRS', '2021-04-18', 'CESADO', @g1),
('40999000', 'Diego', 'Ponce Rojas', 'Vigilante', 'GRS', '2023-01-25', 'ACTIVO', @g1),
('41000111', 'Eduardo', 'Salinas Cruz', 'Supervisor turno', 'GRS', '2018-07-30', 'ACTIVO', @g1),
('41111222', 'Hector', 'Morales Paz', 'Vigilante', 'TERCERO', '2022-11-08', 'ACTIVO', @g2),
('41222333', 'Ivan', 'Gutierrez Leon', 'Vigilante', 'GRS', '2021-05-14', 'ACTIVO', @g2),
('41333444', 'Javier', 'Ortiz Campos', 'Operador pozo', 'GRS', '2020-12-01', 'ACTIVO', @g2),
('41444555', 'Kevin', 'Aguilar Rios', 'Vigilante', 'GRS', '2023-08-22', 'ACTIVO', @g2),
('41555666', 'Leonardo', 'Navarro Saez', 'Balancero', 'TERCERO', '2022-04-05', 'ACTIVO', @g2),
('41666777', 'Manuel', 'Duran Fuentes', 'Vigilante', 'GRS', '2019-09-17', 'ACTIVO', @g2),
('41777888', 'Nicolas', 'Herrera Luna', 'Vigilante', 'GRS', '2024-01-10', 'ACTIVO', @g2),
('41888999', 'Oscar', 'Cardenas Vela', 'Supervisor', 'GRS', '2017-06-25', 'ACTIVO', @g2),
('41999000', 'Pablo', 'Ibarra Costa', 'Vigilante', 'TERCERO', '2023-03-30', 'ACTIVO', @g2),
('42000111', 'Raul', 'Benites Arce', 'Vigilante', 'GRS', '2021-10-12', 'ACTIVO', @g2),
('42111222', 'Sergio', 'Moya Tello', 'Vigilante', 'GRS', '2022-07-07', 'ACTIVO', @g1),
('42222333', 'Tomas', 'Reyes Acosta', 'Almacenero', 'TERCERO', '2023-05-19', 'ACTIVO', @g1),
('42333444', 'Ulises', 'Camacho Pinto', 'Vigilante', 'GRS', '2020-08-28', 'ACTIVO', @g2),
('42444555', 'Victor', 'Silva Wong', 'Operador cerco', 'GRS', '2021-12-03', 'ACTIVO', @g1),
('42555666', 'Walter', 'Chavez Oro', 'Vigilante', 'GRS', '2022-02-14', 'ACTIVO', @g2),
('42666777', 'Xavier', 'Ledesma Roca', 'Vigilante', 'TERCERO', '2023-11-01', 'ACTIVO', @g1),
('42777888', 'Yuri', 'Palacios Vera', 'Balancero', 'GRS', '2019-04-09', 'ACTIVO', @g2),
('42888999', 'Alonso', 'Miranda Cho', 'Vigilante', 'GRS', '2024-03-15', 'ACTIVO', @g1),
('42999000', 'Bruno', 'Valdez Sosa', 'Vigilante', 'GRS', '2021-06-21', 'CESADO', @g2),
('43000111', 'Cesar', 'Farfan Nunez', 'Supervisor turno', 'GRS', '2018-10-30', 'ACTIVO', @g2),
('43111222', 'Daniel', 'Espinoza Cano', 'Vigilante', 'TERCERO', '2022-12-18', 'ACTIVO', @g1),
('43222333', 'Enrique', 'Zevallos Prado', 'Vigilante', 'GRS', '2020-05-05', 'ACTIVO', @g1),
('43333444', 'Felipe', 'Avalos Rique', 'Operador pozo', 'GRS', '2023-02-28', 'ACTIVO', @g2),
('43444555', 'Gabriel', 'Suarez Milla', 'Vigilante', 'GRS', '2021-09-11', 'ACTIVO', @g2),
('43555666', 'Hugo', 'Cordova Tai', 'Vigilante', 'GRS', '2022-08-24', 'ACTIVO', @g1),
('43666777', 'Ismael', 'Bartra Inga', 'Vigilante', 'TERCERO', '2023-07-06', 'ACTIVO', @g2),
('43777888', 'Julio', 'Talledo Osorio', 'Almacenero', 'GRS', '2019-02-17', 'ACTIVO', @g1),
('43888999', 'Karim', 'Lozano Uribe', 'Vigilante', 'GRS', '2024-04-02', 'ACTIVO', @g2),
('43999000', 'Lorenzo', 'Paredes Anti', 'Vigilante', 'GRS', '2021-01-08', 'ACTIVO', @g1),
('44000111', 'Marco', 'Zeballos Paz', 'Vigilante', 'GRS', '2022-10-20', 'ACTIVO', @g2);

INSERT INTO bio_personal_capacitacion (personal_id, capacitacion_id, fecha_realizada, fecha_vence)
SELECT p.id, c.id, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 9 MONTH)
FROM bio_personal p
JOIN bio_capacitacion c ON c.codigo = 'BIO-01'
WHERE p.estado = 'ACTIVO' LIMIT 35;

INSERT INTO bio_personal_capacitacion (personal_id, capacitacion_id, fecha_realizada, fecha_vence)
SELECT p.id, c.id, DATE_SUB(CURDATE(), INTERVAL 14 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
FROM bio_personal p
JOIN bio_capacitacion c ON c.codigo = 'BIO-02'
WHERE p.id % 7 = 0 AND p.estado = 'ACTIVO' LIMIT 5;

-- Insumos base (almacén)
INSERT IGNORE INTO `bio_insumo` (`codigo`, `nombre`, `categoria`, `unidad`) VALUES
('IND-001', 'Overol', 'INDUMENTARIA', 'UND'),
('IND-002', 'Botas de jebe', 'INDUMENTARIA', 'PAR'),
('IND-003', 'Gorro descartable', 'INDUMENTARIA', 'UND'),
('EPP-001', 'Mascarilla', 'EPP', 'UND'),
('EPP-002', 'Guantes de nitrilo', 'EPP', 'PAR'),
('DES-001', 'Desinfectante (amonio cuaternario)', 'DESINFECTANTE', 'L'),
('DES-002', 'Alcohol 70%', 'DESINFECTANTE', 'L');
