CREATE DATABASE IF NOT EXISTS betting_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE betting_db;

CREATE TABLE IF NOT EXISTS analisis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_analisis DATE NOT NULL,
    casa_apuestas VARCHAR(100) NOT NULL,
    total_partidos INT NOT NULL DEFAULT 0,
    resumen TEXT,
    exposicion_soles DECIMAL(10,2) DEFAULT 0,
    exposicion_pct_bankroll INT DEFAULT 0,
    exposicion_num_tickets INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analisis_id INT NOT NULL,
    partido VARCHAR(200) NOT NULL,
    mercado VARCHAR(100) NOT NULL,
    seleccion VARCHAR(100) NOT NULL,
    cuota_betano DECIMAL(6,3) NOT NULL,
    prob_estimada DECIMAL(5,4) NOT NULL,
    prob_implicita_cuota DECIMAL(5,4) NOT NULL,
    value_pct DECIMAL(6,2) NOT NULL,
    confianza ENUM('ALTA','MEDIA','BAJA') NOT NULL DEFAULT 'MEDIA',
    stake_unidades DECIMAL(6,2) NOT NULL DEFAULT 1,
    stake_soles DECIMAL(8,2) NOT NULL DEFAULT 1,
    razon TEXT,
    riesgo ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio',
    resultado ENUM('pendiente','ganado','perdido','void') NOT NULL DEFAULT 'pendiente',
    ganancia_soles DECIMAL(8,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partidos_sin_value (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analisis_id INT NOT NULL,
    partido VARCHAR(200) NOT NULL,
    razon TEXT,
    cuota_mas_cercana VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (analisis_id) REFERENCES analisis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
