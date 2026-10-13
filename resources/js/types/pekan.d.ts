export interface FinanceAccount {
  id: string;
  name: string;
  account_type: 'cash' | 'bank' | 'ewallet' | 'investment';
  currency: string;
  opening_balance_minor: number;
  current_balance_minor: number;
  is_active: boolean;
}

export interface FinanceCategory {
  id: string;
  name: string;
  category_type: 'income' | 'expense';
  parent_id?: string | null;
  icon?: string;
  is_active: boolean;
}

export interface TransactionItem {
  id?: string;
  item_name: string;
  quantity: number;
  price_minor: number;
  discount_minor: number;
  total_minor: number;
  notes?: string;
}

export interface Transaction {
  id: string;
  account_id: string;
  account_name?: string;
  category_id?: string | null;
  category_name?: string | null;
  type: 'income' | 'expense' | 'transfer' | 'savings';
  amount_minor: number;
  currency: string;
  transaction_date: string;
  description?: string;
  merchant_name?: string;
  receipt_number?: string;
  payment_method?: string;
  subtotal_minor?: number;
  tax_minor?: number;
  service_charge_minor?: number;
  receipt_discount_minor?: number;
  notes?: string;
  created_at: string;
  items?: TransactionItem[];
  attachments?: { id: string; filename: string; url: string }[];
}

export interface FinanceBudget {
  id: string;
  name: string;
  category_id?: string;
  category_name?: string;
  amount_limit_minor: number;
  spent_amount_minor: number;
  progress_percent: number;
  period: 'weekly' | 'monthly' | 'yearly';
  start_date: string;
  alert_threshold_pct: number;
  status: 'active' | 'exceeded' | 'archived';
}

export interface FinanceSavings {
  id: string;
  name: string;
  target_amount_minor: number;
  current_amount_minor: number;
  progress_percent: number;
  target_date?: string;
  status: 'active' | 'completed' | 'cancelled';
}

export interface FinanceReminder {
  id: string;
  title: string;
  amount_minor: number;
  due_date: string;
  repeat_interval: string;
  status: 'pending' | 'paid' | 'overdue';
  total_tenor?: number;
  current_tenor?: number;
}

export interface ReceiptExtraction {
  merchant_name: string;
  receipt_number?: string;
  transaction_date?: string;
  currency?: string;
  subtotal_minor: number;
  tax_minor: number;
  service_charge_minor: number;
  discount_minor: number;
  total_minor: number;
  items: {
    item_name: string;
    quantity: number;
    price_minor: number;
    discount_minor: number;
    total_minor: number;
  }[];
}
