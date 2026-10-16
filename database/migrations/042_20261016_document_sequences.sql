-- Migration 042: concurrency-safe document numbering (Phase 10.5).
-- One counter per branch, document type (sale, receipt, purchase, supplier_payment, expense)
-- and transaction day. DocumentNumberService increments it atomically inside the posting
-- transaction. Idempotent.

CREATE TABLE IF NOT EXISTS document_sequences(
    location_id BIGINT UNSIGNED NOT NULL,
    doc_type VARCHAR(20) NOT NULL,
    period CHAR(8) NOT NULL,
    last_value INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(location_id, doc_type, period),
    CONSTRAINT fk_document_sequences_location FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;
