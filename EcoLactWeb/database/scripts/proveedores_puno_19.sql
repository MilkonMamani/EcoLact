-- EcoLact: 19 proveedores de prueba con nombres plausibles de Puno.
-- No son personas reales verificadas. Importar en la base ecolactv1 desde phpMyAdmin.
-- Usuario y contraseña: nombre123, siempre en minusculas.

START TRANSACTION;

-- Primero crea las cuentas de acceso en users. La contrasena esta guardada
-- como hash bcrypt, nunca como texto plano.
INSERT IGNORE INTO `users`
    (`role_id`, `username`, `password`, `estado`, `debe_cambiar_password`, `ultimo_acceso`, `remember_token`, `created_at`, `updated_at`)
VALUES
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'juan123', '$2y$10$obKt4KUbe3j/rfJUyKuuxucO24qqJTZG1E5NhPwcDEG5D2o1vuUqK', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'maria123', '$2y$10$JG22PO/jr00LX0Rb2zK9ZO.nw93j.9sEubPWeEfNUxTT13x2cq1UC', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'pedro123', '$2y$10$8LCt6o81VdbigFWLfSlsOO4UMN8RKb8BOWtkghmP.1tQbeBi0yUAW', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'rosa123', '$2y$10$6JCBWACqGHa9Tf/Nvmyzl.evZbIHpRkHVfzG2IblNaUHTghd0YE2S', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'luis123', '$2y$10$cEAuTF4VEVAdM4fk/ZwA/OHwp4Ekvf1jGe.EJz1uNIBocNJaO5rAS', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'ana123', '$2y$10$4vUTAxBZR0iGI637PdQ5EeAwTVdd1EVnKCK7lUpClyEpFxFMsN8uG', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'carlos123', '$2y$10$fDh8jJUfnYJbIl6rIKUTSeermJSXjta24ihToxza.sw94/yIh5v6i', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'elena123', '$2y$10$OPf2v1kcB1BSghmye88AvuWFHVcvbVP421zZ..YEZpSp3w4Hdj.hG', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'jose123', '$2y$10$3m8uDu20oB15J4jVy46sP.xEk/n7YfywFj0AplUWhfR4seW.EBkr6', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'lucia123', '$2y$10$1LIc2EATF1KlYChy7la0Ce8rCgpQYgjwzClSk2cDZe97gJ9fTyB0W', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'miguel123', '$2y$10$7RH48OJp7uwIbb1P.H3LiO6CafB1QuFxDzEsC3E/eYevqvyxkVSIe', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'sandra123', '$2y$10$PhwTItd0B9hY5r20Y0.EDO8x2/7joYcDmKAJDv0k07rydf/t2ZPvK', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'wilson123', '$2y$10$aRkm4pBK.yWC7I/WyAosFeLQfPA1wx8W/Wg.D/w5vsguH4GSYg9WC', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'diana123', '$2y$10$d8tALa/ZVgdE3biJrV3BeejFmU/rSdPqYRDSmadsGmEaR9TWimuce', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'edwin123', '$2y$10$86cuoJKtUCDvhA7cPMCIq.UtHPqq1Nt8isgbCpCza0BWLGZIQ0ItG', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'karina123', '$2y$10$V4Sdr7vl7KKrrpH3vz.t5u0S/6.Ssvy8ZYgB3spmDBBaZJOZHAPgm', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'raul123', '$2y$10$Q0inLb3gGck/1JLgUof1OOgy3fxlYXEkQbRF0bwrPTEFqPFzdgfee', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'gloria123', '$2y$10$yjLnVAj8VR4jvGsgr2gty.B2UyYWqunOlyy.H7AEXq.994pVo4Tc2', 'ACTIVO', 1, NULL, NULL, NOW(), NOW()),
    ((SELECT `id` FROM `roles` WHERE `nombre` = 'PROVEEDOR'), 'oscar123', '$2y$10$E/9CfPG00bxc62ILIuVkl.uo9SHvDWeQLVgHyTBWbg/9/hFJ9rVPa', 'ACTIVO', 1, NULL, NULL, NOW(), NOW());

-- Crea los perfiles y relaciona cada uno con una zona activa existente.
INSERT IGNORE INTO `proveedores`
    (`user_id`, `zona_id`, `dni`, `nombres`, `apellidos`, `telefono`, `qr_token`, `estado`, `fecha_registro`, `created_at`, `updated_at`)
VALUES
    ((SELECT `id` FROM `users` WHERE `username` = 'juan123'), 1, '72100001', 'Juan', 'Quispe Mamani', '951700001', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'maria123'), 2, '72100002', 'Maria', 'Mamani Condori', '951700002', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'pedro123'), 3, '72100003', 'Pedro', 'Condori Apaza', '951700003', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'rosa123'), 1, '72100004', 'Rosa', 'Apaza Quispe', '951700004', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'luis123'), 2, '72100005', 'Luis', 'Calcina Mamani', '951700005', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'ana123'), 3, '72100006', 'Ana', 'Choque Condori', '951700006', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'carlos123'), 1, '72100007', 'Carlos', 'Coaquira Apaza', '951700007', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'elena123'), 2, '72100008', 'Elena', 'Huanca Quispe', '951700008', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'jose123'), 3, '72100009', 'Jose', 'Pari Mamani', '951700009', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'lucia123'), 1, '72100010', 'Lucia', 'Chambi Condori', '951700010', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'miguel123'), 2, '72100011', 'Miguel', 'Flores Apaza', '951700011', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'sandra123'), 3, '72100012', 'Sandra', 'Ccama Quispe', '951700012', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'wilson123'), 1, '72100013', 'Wilson', 'Poma Mamani', '951700013', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'diana123'), 2, '72100014', 'Diana', 'Nina Condori', '951700014', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'edwin123'), 3, '72100015', 'Edwin', 'Yujra Apaza', '951700015', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'karina123'), 1, '72100016', 'Karina', 'Vilca Quispe', '951700016', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'raul123'), 2, '72100017', 'Raul', 'Sucaticona Mamani', '951700017', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'gloria123'), 3, '72100018', 'Gloria', 'Mamani Choque', '951700018', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW()),
    ((SELECT `id` FROM `users` WHERE `username` = 'oscar123'), 1, '72100019', 'Oscar', 'Catacora Condori', '951700019', UUID(), 'ACTIVO', CURDATE(), NOW(), NOW());

-- Historial inicial requerido por el seguimiento de zonas.
INSERT INTO `proveedor_zona_historial`
    (`proveedor_id`, `zona_id`, `fecha_inicio`, `fecha_fin`, `motivo`, `created_at`, `updated_at`)
SELECT p.`id`, p.`zona_id`, p.`fecha_registro`, NULL, 'Registro inicial del proveedor', NOW(), NOW()
FROM `proveedores` p
JOIN `users` u ON u.`id` = p.`user_id`
WHERE u.`username` IN ('juan123', 'maria123', 'pedro123', 'rosa123', 'luis123', 'ana123', 'carlos123', 'elena123', 'jose123', 'lucia123', 'miguel123', 'sandra123', 'wilson123', 'diana123', 'edwin123', 'karina123', 'raul123', 'gloria123', 'oscar123')
  AND NOT EXISTS (
      SELECT 1 FROM `proveedor_zona_historial` h
      WHERE h.`proveedor_id` = p.`id` AND h.`fecha_fin` IS NULL
  );

COMMIT;

-- Credenciales: juan123/juan123, maria123/maria123, etc.
