export interface OptimizationRuleCondition {
  metric: 'spend' | 'impressions' | 'clicks' | 'sales' | 'roas';
  operator: 'greater_than' | 'less_than' | 'equal' | 'greater_than_or_equal' | 'less_than_or_equal';
  value: number;
}

export interface OptimizationRule {
  id: number;
  workspace_id: number;
  name: string;
  description: string | null;
  target: 'campaign' | 'ad_set';
  action: 'increase_budget_fixed' | 'decrease_budget_fixed' | 'increase_budget_percentage' | 'decrease_budget_percentage';
  action_value: number | null;
  conditions: OptimizationRuleCondition[];
  status: 'active' | 'paused';
  created_at: string;
  updated_at: string;
}
