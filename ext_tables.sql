CREATE TABLE bernard_queues (
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (name)
) ENGINE = InnoDB;

CREATE TABLE bernard_messages (
  id      INT UNSIGNED AUTO_INCREMENT NOT NULL,
  queue   VARCHAR(255)                NOT NULL,
  message LONGTEXT                    NOT NULL,
  visible TINYINT(1) DEFAULT '1'      NOT NULL,
  sentAt  DATETIME                    NOT NULL,
  INDEX IDX_28999D87FFD7F635BADAD2C7AB0E859 (queue, sentAt, visible),
  PRIMARY KEY (id)
) ENGINE = InnoDB;