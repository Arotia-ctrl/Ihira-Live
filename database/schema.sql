CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS competitors(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  passage_number INTEGER NOT NULL,
  full_name TEXT NOT NULL,
  stage_name TEXT,
  church TEXT,
  church_org TEXT,
  photo TEXT,
  description TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS votes(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  competitor_id INTEGER NOT NULL UNIQUE REFERENCES competitors(id) ON DELETE CASCADE,
  vote_count INTEGER NOT NULL CHECK(vote_count >= 0),
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS event_settings(
  id INTEGER PRIMARY KEY CHECK(id = 1),
  event_name TEXT NOT NULL,
  event_date TEXT,
  organization_name TEXT,
  logo TEXT,
  results_validated INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  phase INTEGER NOT NULL DEFAULT 1,
  qualify_p1 INTEGER NOT NULL DEFAULT 5,
  qualify_p2 INTEGER NOT NULL DEFAULT 3,
  jury_max INTEGER NOT NULL DEFAULT 100,
  logo_left TEXT,
  logo_right TEXT,
  jury_count INTEGER NOT NULL DEFAULT 3
);
CREATE TABLE IF NOT EXISTS live_state(
  id INTEGER PRIMARY KEY CHECK(id = 1),
  scene TEXT NOT NULL DEFAULT 'accueil',
  competitor_id INTEGER REFERENCES competitors(id) ON DELETE SET NULL,
  reveal_step INTEGER NOT NULL DEFAULT 0,
  seq INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pending_scene TEXT,
  pending_competitor_id INTEGER
);
CREATE TABLE IF NOT EXISTS publications(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  publication_type TEXT NOT NULL,
  competitor_id INTEGER REFERENCES competitors(id) ON DELETE SET NULL,
  publication_data TEXT,
  published_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_by INTEGER REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS audit_logs(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id),
  action TEXT NOT NULL,
  description TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS scores(
  competitor_id INTEGER NOT NULL REFERENCES competitors(id) ON DELETE CASCADE,
  phase INTEGER NOT NULL CHECK(phase BETWEEN 1 AND 3),
  jury_score REAL CHECK(jury_score IS NULL OR jury_score >= 0),
  public_votes INTEGER CHECK(public_votes IS NULL OR public_votes >= 0),
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(competitor_id, phase)
);
CREATE TABLE IF NOT EXISTS phase_validation(
  phase INTEGER PRIMARY KEY CHECK(phase BETWEEN 1 AND 3),
  validated INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS jury_notes(
  competitor_id INTEGER NOT NULL REFERENCES competitors(id) ON DELETE CASCADE,
  phase INTEGER NOT NULL CHECK(phase BETWEEN 1 AND 3),
  juror INTEGER NOT NULL CHECK(juror >= 1),
  note REAL NOT NULL CHECK(note >= 0),
  PRIMARY KEY(competitor_id, phase, juror)
);
CREATE TABLE IF NOT EXISTS text_overrides(
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL
);
