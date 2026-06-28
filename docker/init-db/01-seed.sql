-- Base Schema
CREATE TABLE IF NOT EXISTS bank_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    provider_id TEXT NOT NULL,
    account_id_hashed TEXT UNIQUE NOT NULL,
    encrypted_account_name TEXT, 
    encrypted_balance TEXT,
    currency TEXT DEFAULT 'GBP',
    last_updated TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_id UUID REFERENCES bank_accounts(id) ON DELETE CASCADE,
    user_id UUID NOT NULL,
    truelayer_id TEXT UNIQUE NOT NULL,
    date DATE NOT NULL,
    encrypted_amount TEXT NOT NULL,
    encrypted_description TEXT,
    category TEXT, 
    is_pending BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Seed Test Data
-- Test User UUID: f6b1e9eb-03a4-470b-a5da-8de341f3c15a (Matched to dashboard placeholder)
INSERT INTO bank_accounts (id, user_id, provider_id, account_id_hashed, encrypted_account_name, encrypted_balance)
VALUES 
('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a', 'ob-amex', 'hash-amex-123', 'Encrypted Amex', 'Encrypted 2777.09'),
('b1eebc99-9c0b-4ef8-bb6d-6bb9bd380b22', 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a', 'halifax', 'hash-halifax-456', 'Encrypted Halifax', 'Encrypted 1918.64');

INSERT INTO transactions (account_id, user_id, truelayer_id, date, encrypted_amount, encrypted_description)
VALUES 
('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a', 'tx-1', '2026-06-20', 'Encrypted -45.00', 'Encrypted Waitrose'),
('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a', 'tx-2', '2026-06-19', 'Encrypted -12.50', 'Encrypted Starbucks');
