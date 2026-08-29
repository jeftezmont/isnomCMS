CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  is_system TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL,
  assigned_by INT UNSIGNED NULL,
  INDEX idx_user_roles_role (role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  INDEX idx_remember_tokens_user (user_id),
  INDEX idx_remember_tokens_expires (expires_at),
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  email VARCHAR(190) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
  INDEX idx_login_attempts_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webauthn_challenges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('registration','authentication') NOT NULL,
  user_id INT UNSIGNED NULL,
  challenge VARCHAR(96) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_webauthn_challenges_lookup (type, challenge, expires_at),
  INDEX idx_webauthn_challenges_user (user_id),
  CONSTRAINT fk_webauthn_challenges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webauthn_credentials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  credential_id VARCHAR(512) NOT NULL UNIQUE,
  public_key TEXT NOT NULL,
  counter INT UNSIGNED NOT NULL DEFAULT 0,
  transports VARCHAR(255) NULL,
  label VARCHAR(120) NOT NULL DEFAULT 'Passkey',
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  INDEX idx_webauthn_credentials_user (user_id),
  CONSTRAINT fk_webauthn_credentials_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_two_factor (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  encrypted_secret TEXT NOT NULL,
  enabled_at DATETIME NULL,
  last_used_step BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_user_two_factor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE two_factor_recovery_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_two_factor_recovery_user (user_id, used_at),
  CONSTRAINT fk_two_factor_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE two_factor_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  INDEX idx_two_factor_attempts_lookup (user_id, ip_address, attempted_at),
  CONSTRAINT fk_two_factor_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL,
  INDEX idx_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  excerpt TEXT NOT NULL,
  content MEDIUMTEXT NOT NULL,
  featured_image VARCHAR(255) NULL,
  status ENUM('draft','published','private') NOT NULL DEFAULT 'draft',
  preview_token VARCHAR(64) NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  author_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(320) NULL,
  og_image VARCHAR(255) NULL,
  FULLTEXT KEY ft_posts_search (title, excerpt, content),
  INDEX idx_posts_preview_token (preview_token),
  INDEX idx_posts_slug_status (slug, status),
  INDEX idx_posts_published (status, published_at),
  CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  `key` VARCHAR(80) NOT NULL PRIMARY KEY,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nav_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu VARCHAR(40) NOT NULL,
  label VARCHAR(120) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_nav_links_menu (menu, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  CONSTRAINT fk_post_tags_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(160) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE podcasts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(220) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  short_description VARCHAR(320) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  author VARCHAR(190) NOT NULL,
  owner_name VARCHAR(190) NOT NULL,
  owner_email VARCHAR(190) NOT NULL,
  language VARCHAR(20) NOT NULL DEFAULT 'es-MX',
  category_primary VARCHAR(120) NOT NULL,
  category_secondary VARCHAR(120) NULL,
  copyright VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  cover_image VARCHAR(255) NOT NULL,
  explicit TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  apple_podcasts_url VARCHAR(255) NULL,
  spotify_url VARCHAR(255) NULL,
  episodes_per_page SMALLINT UNSIGNED NOT NULL DEFAULT 9,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_podcasts_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE podcast_episodes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  podcast_id INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL,
  summary TEXT NOT NULL,
  show_notes MEDIUMTEXT NOT NULL,
  audio_source ENUM('local','dropbox') NOT NULL,
  audio_local_path VARCHAR(255) NULL,
  audio_original_url TEXT NULL,
  audio_url TEXT NOT NULL,
  audio_mime_type VARCHAR(80) NOT NULL,
  audio_file_size BIGINT UNSIGNED NOT NULL,
  duration VARCHAR(20) NULL,
  image_url VARCHAR(255) NULL,
  author VARCHAR(190) NULL,
  episode_number INT UNSIGNED NULL,
  season_number INT UNSIGNED NULL,
  episode_type ENUM('full','trailer','bonus') NOT NULL DEFAULT 'full',
  explicit TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  guid CHAR(36) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_podcast_episode_slug (podcast_id, slug),
  INDEX idx_podcast_episodes_listing (podcast_id, status, published_at),
  INDEX idx_podcast_episodes_status (status, published_at),
  CONSTRAINT fk_podcast_episodes_podcast FOREIGN KEY (podcast_id) REFERENCES podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, slug, description, created_at) VALUES
('Tecnología', 'tecnologia', 'Apple, hardware, herramientas y cultura tecnológica.', NOW()),
('Teología', 'teologia', 'Notas sobre fe, símbolos y esperanza.', NOW()),
('Desarrollo', 'desarrollo', 'Tecnología, código y construcción de proyectos.', NOW()),
('Diseño', 'diseno', 'Sistemas visuales, intención y estética.', NOW()),
('Música', 'musica', 'Listas, escenas y registros sonoros.', NOW()),
('Arte', 'arte', 'Observaciones visuales y procesos creativos.', NOW());

INSERT INTO site_settings (`key`, `value`, updated_at) VALUES
('accent', '#ff4f9a', NOW()),
('accent_soft', '#ffe5f0', NOW()),
('coming_soon_mode', '0', NOW()),
('home_bio_1', 'Ingeniero en Sistemas, desarrollador web y creador digital con base en la Ciudad de México. Trabajo de manera independiente diseñando y construyendo proyectos donde convergen **tecnología, diseño y creatividad**. Me interesa crear experiencias digitales que no solo funcionen, sino que tengan identidad, intención y una razón de ser.', NOW()),
('home_bio_2', 'Mi trabajo explora el encuentro entre **código y diseño, ingeniería y creatividad, música e imagen, tecnología y significado**. Fuera de lo digital, encuentro en la fotografía, el podcasting, la lectura y la escritura creativa otras formas de explorar y comunicar ideas. También escribo y reflexiono sobre tecnología, música, cultura, diseño y teología.', NOW()),
('home_bio_3', 'Creo en las ideas que nacen de la intuición, pero se sostienen con estructura, estrategia y atención al detalle. Ya sea una interfaz, una identidad, una fotografía, un texto o una experiencia completa, busco convertir ideas en algo que **funcione, comunique y signifique**.', NOW()),
('discord_url', 'https://discord.gg/nCRrSAwVph', NOW()),
('instagram_url', 'https://instagram.com/jeftezmont', NOW()),
('soundcloud_url', 'https://soundcloud.com/jeftezmont', NOW()),
('threads_url', 'https://www.threads.com/@jeftezmont', NOW()),
('social_links', '[{"label":"Instagram","url":"https://instagram.com/jeftezmont"},{"label":"SoundCloud","url":"https://soundcloud.com/jeftezmont"},{"label":"Threads","url":"https://www.threads.com/@jeftezmont"},{"label":"Discord","url":"https://discord.gg/nCRrSAwVph"},{"label":"Blog","url":"/blog"}]', NOW());

INSERT INTO nav_links (menu, label, url, sort_order, created_at, updated_at) VALUES
('blog', 'Inicio', '/blog', 1, NOW(), NOW()),
('blog', 'Tecnología', '/blog?category=tecnologia', 2, NOW(), NOW()),
('blog', 'Teología', '/blog?category=teologia', 3, NOW(), NOW()),
('blog', 'Música', '/blog?category=musica', 4, NOW(), NOW());
