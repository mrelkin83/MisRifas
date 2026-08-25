-- Schema base para Gammu SMSD SQLite outbox
CREATE TABLE IF NOT EXISTS outbox (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    InsertIntoDB DATETIME,
    SendingDateTime DATETIME,
    DestinationNumber TEXT NOT NULL,
    TextDecoded TEXT NOT NULL,
    Coding TEXT DEFAULT 'Default_No_Compression',
    UDH TEXT,
    Class INTEGER DEFAULT -1,
    Text TEXT,
    SenderID TEXT,
    CreatorID TEXT,
    MultiPart TEXT,
    RelativeValidity INTEGER,
    DeliveryReport TEXT,
    State TEXT
);

CREATE INDEX IF NOT EXISTS idx_destination ON outbox(DestinationNumber);
