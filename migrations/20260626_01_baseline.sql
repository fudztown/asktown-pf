-- 20260626_01_baseline.sql
-- Baseline schema from config/schema.sql

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

-- RLS
ALTER TABLE bank_accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;

DO $$ 
BEGIN
    IF EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'auth') THEN
        DROP POLICY IF EXISTS "Users can only view their own accounts" ON bank_accounts;
        CREATE POLICY "Users can only view their own accounts" 
        ON bank_accounts FOR ALL USING (auth.uid() = user_id);

        DROP POLICY IF EXISTS "Users can only view their own transactions" ON transactions;
        CREATE POLICY "Users can only view their own transactions" 
        ON transactions FOR ALL USING (auth.uid() = user_id);
    END IF;
END $$;
