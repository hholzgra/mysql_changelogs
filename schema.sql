DROP TABLE IF EXISTS version;

CREATE TABLE version (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100),
  major INT,
  minor INT,
  patch INT,
  extra VARCHAR(10),
  released DATE,
  product VARCHAR(100), -- normalize?
  state VARCHAR(100),
  PRIMARY KEY(id)
) ENGINE=MyISAM;



DROP TABLE IF EXISTS entry;

CREATE TABLE entry (
  id INT NOT NULL AUTO_INCREMENT,
  version_id INT,
  section TEXT,
  plain_text TEXT,
  html_text TEXT,
  PRIMARY KEY(id)
) ENGINE=MyISAM;


DROP TABLE IF EXISTS bug;

CREATE TABLE bug (
  id INT NOT NULL AUTO_INCREMENT,
  bug_system ENUM('MySQL', 'Oracle'),
  bug_number BIGINT,
  synopsis TEXT,
  PRIMARY KEY(id)
) ENGINE=MyISAM;


DROP TABLE IF EXISTS entry_bug;

CREATE TABLE entry_bug (
  entry_id INT,
  bug_id INT,
  PRIMARY KEY(entry_id, bug_id)
) ENGINE=MyISAM;

