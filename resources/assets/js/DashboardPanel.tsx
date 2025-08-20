import React, { useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Progress } from "@/components/ui/progress"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { toast, Toaster } from 'sonner';
import { FileText, Clock, Settings as SettingsIcon, CheckCircle2, AlertTriangle } from 'lucide-react';
import { Bar, BarChart, XAxis, YAxis, LabelList } from "recharts"
import { ChartConfig, ChartContainer } from "@/components/ui/chart"

interface DashboardData {
  last_export: { timestamp?: number; count?: number };
  exported_files_count: number;
  export_dir: string;
  counts: Record<string, number>;
  next_scheduled: number | null;
  auto_export: boolean;
}

declare global {
  interface Window {
    worddown_variables?: {
      restUrl?: string;
      restNonce?: string;
      strings?: Record<string, string>;
    };
  }
}

// Use the full API base path from PHP-localized variable
const API_BASE = (window.worddown_variables?.restUrl || '/wp-json/worddown/v1/').replace(/\/$/, '');
const REST_NONCE: string = window.worddown_variables?.restNonce || '';

// Custom translation function to use localized strings from PHP
function __(text: string, domain = 'worddown') {
  if (
    window.worddown_variables &&
    window.worddown_variables.strings &&
    window.worddown_variables.strings[text]
  ) {
    return window.worddown_variables.strings[text];
  }
  return text;
}

export default function DashboardPanel() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [settings, setSettings] = useState<Record<string, any> | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [exportStatus, setExportStatus] = useState<any>(null);
  const [progress, setProgress] = useState(0);

  // Poll export status every 5 seconds when export is running
  useEffect(() => {
    let interval: NodeJS.Timeout;
    
    if (exporting && exportStatus?.status === 'running') {
      interval = setInterval(async () => {
        await checkExportStatus();
      }, 5000);
    }
    
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [exporting, exportStatus]);

  useEffect(() => {
    fetchData();
    
    // Check if there's already an export running
    checkExportStatus();
  }, []);

  async function checkExportStatus() {
    try {
      const res = await fetch(`${API_BASE}/export-status`, {
        headers: { 'X-WP-Nonce': REST_NONCE },
      });
      const status = await res.json();

      if (status.status === 'running') {
        // Update export status and progress
        setExportStatus(status);
        setProgress(status.progress_percentage || 0);
        
        // If we're not currently exporting, start the export state
        if (!exporting) {
          setExporting(true);
        }
      } else if (status.status === 'completed' || status.status === 'cancelled' || status.status === 'failed') {
        // Export is finished, stop polling and update UI
        setExporting(false);
        setExportStatus(null);
        setProgress(0);
        
        // Refresh dashboard data to update last export alert and bar chart
        try {
          setLoading(true); // Show loading state during refresh
          await fetchData();
        } catch (error) {
          console.error('Failed to refresh dashboard data:', error);
        } finally {
          setLoading(false); // Hide loading state
        }
        
        // Show appropriate toast message
        if (status.status === 'completed') {
          toast.success(__('Export completed successfully!', 'worddown'));
        } else if (status.status === 'cancelled') {
          toast.info(__('Export was cancelled.', 'worddown'));
        } else {
          toast.error(__('Export failed.', 'worddown'));
        }
      } else if (status.status === 'idle') {
        if (exporting) {
          setExporting(false);
          setExportStatus(null);
          setProgress(0);
        }
      }
    } catch (e) {
      console.error('Failed to check export status:', e);
    }
  }

  async function fetchData() {
    try {
      const [dashboardRes, settingsRes] = await Promise.all([
        fetch(`${API_BASE}/dashboard`, {
          headers: { 'X-WP-Nonce': REST_NONCE },
        }).then(r => r.json()),
        fetch(`${API_BASE}/settings`, {
          headers: { 'X-WP-Nonce': REST_NONCE },
        }).then(r => r.json()),
      ]);

      setData(dashboardRes);
      setSettings(settingsRes);
      setLoading(false);

    } catch (e) {
      toast.error(__('Failed to load dashboard data.', 'worddown'));
      setLoading(false);
    }
  }

  async function handleExport(e: React.FormEvent) {
    e.preventDefault();

    setExporting(true);
    setProgress(0);

    try {
      const res = await fetch(`${API_BASE}/local-export`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': REST_NONCE
        },
      });

      const result = await res.json();

      if (result.status === 'started') {
        toast.success(__('Export started successfully!', 'worddown'));
        setExportStatus({
          status: 'running',
          total_posts: result.total_posts,
          processed: 0,
          exported: 0,
          progress_percentage: 0
        });
      } else {
        toast.error(result.message || __('Export failed to start.', 'worddown'));
        setExporting(false);
      }

    } catch (e) {
      toast.error(__('Export failed to start.', 'worddown'));
      setExporting(false);
    }
  }

  async function handleCancelExport() {
    try {
      const res = await fetch(`${API_BASE}/cancel-export`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': REST_NONCE
        },
      });

      const result = await res.json();

      if (result.success) {
        toast.success(__('Export cancelled successfully.', 'worddown'));
        setExporting(false);
        setExportStatus(null);
        setProgress(0);
      } else {
        toast.error(result.message || __('Failed to cancel export.', 'worddown'));
      }

    } catch (e) {
      toast.error(__('Failed to cancel export.', 'worddown'));
    }
  }

  if (loading) return (
    <div className="worddown-dashboard-panel mt-4">
      <div className="max-w-5xl space-y-8">
        {/* Top stats grid skeleton */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[0, 1, 2].map(i => (
            <div key={i} className="relative flex items-center rounded-2xl border border-[#e1e5e9] bg-white px-7 py-9 shadow-[0_4px_12px_rgba(0,0,0,0.05)]">
              <div className="stat-icon mr-5 rounded-xl p-4 bg-gray-200">
                <Skeleton className="w-10 h-10 rounded" />
              </div>
              <div className="stat-content flex-1">
                <Skeleton className="h-7 w-16 mb-2 rounded" />
                <Skeleton className="h-4 w-24 rounded" />
              </div>
            </div>
          ))}
        </div>
        {/* Bar chart skeleton */}
        <Card className="bg-white">
          <CardHeader>
            <Skeleton className="h-6 w-48 rounded mb-2" />
          </CardHeader>
          <CardContent>
            <div className="w-full" style={{ height: 350 }}>
              <div className="flex items-end justify-between h-full gap-4">
                {[0, 1, 2, 3, 4].map(i => (
                  <div key={i} className="flex-1 flex flex-col items-center">
                    <Skeleton className="w-16 rounded mb-2" style={{ height: Math.random() * 200 + 100 }} />
                    <Skeleton className="h-4 w-18 rounded" />
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
        {/* Export form card skeleton */}
        <Card className="bg-white">
          <CardContent className="py-8 px-8">
            <div className="flex flex-col gap-4">
              <Skeleton className="h-15 w-1/2 mb-4 rounded" />
              <div className="flex flex-col md:flex-row md:items-center md:gap-8 gap-2 mt-2">
                <Skeleton className="h-10 w-40 rounded mb-2" />
                <Skeleton className="h-4 w-48 rounded" />
              </div>
            </div>
          </CardContent>
        </Card>
        {/* Static export info card skeleton */}
        <Card className="bg-white">
          <CardHeader>
            <Skeleton className="h-6 w-40 rounded mb-2" />
          </CardHeader>
          <CardContent>
            <div className="mb-3 flex flex-col gap-2">
              <Skeleton className="h-4 w-32 rounded" />
              <Skeleton className="h-4 w-48 rounded" />
            </div>
            <div className="mb-3 flex items-center gap-2">
              <Skeleton className="h-5 w-5 rounded-full" />
              <Skeleton className="h-4 w-48 rounded" />
            </div>
            <div className="mb-3 flex items-center gap-2">
              <Skeleton className="h-4 w-24 rounded" />
              <Skeleton className="h-4 w-40 rounded" />
            </div>
            <div className="mt-6">
              <Skeleton className="h-5 w-32 rounded mb-2" />
              <div className="flex flex-wrap gap-4">
                {[0,1,2].map(i => (
                  <div key={i} className="flex flex-col items-center bg-white border border-[#e1e5e9] rounded-lg px-4 py-2 min-w-[100px]">
                    <Skeleton className="h-6 w-8 rounded mb-1" />
                    <Skeleton className="h-3 w-16 rounded" />
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
  if (!data) return null;

  const lastExportDate = data.last_export?.timestamp
    ? new Date(data.last_export.timestamp * 1000)
    : null;
  const nextScheduledDate = data.next_scheduled
    ? new Date(data.next_scheduled * 1000)
    : null;

  let chartData = Object.entries(data.counts).map(([type, count]) => ({ type, count }));
  chartData = chartData.sort((a, b) => b.count - a.count);
  const maxValue = Math.max(...chartData.map(d => d.count), 1);

  return (
    <div className="worddown-dashboard-panel mt-4">
      <div className="max-w-5xl space-y-8">
        {/* Top stats grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Exported Files */}
          <div className="relative group overflow-hidden flex items-center rounded-2xl border border-[#e1e5e9] bg-white px-7 py-9 shadow-[0_4px_12px_rgba(0,0,0,0.05)] transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] hover:-translate-y-1 hover:shadow-[0_12px_24px_rgba(0,0,0,0.12)] hover:border-[#c3c4c7]">
            <div className="absolute top-0 left-0 right-0 h-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300" style={{background: 'linear-gradient(90deg, #0073aa 0%, #005a87 100%)'}} />
            <div className="stat-icon mr-5 rounded-xl p-4 shadow-[0_4px_12px_rgba(0,115,170,0.2)]" style={{background: 'linear-gradient(135deg, #0073aa 0%, #005a87 100%)'}}>
              <FileText className="w-10 h-10 text-white" />
            </div>
            <div className="stat-content flex-1">
              <h3 className="!m-0 !mb-2 !text-[1.8em] font-bold leading-tight bg-gradient-to-r from-[#1d2327] to-[#50575e] bg-clip-text text-transparent">{data.exported_files_count}</h3>
              <p className="!m-0 text-[#646970] text-[0.95em] font-medium uppercase tracking-wide">{__('Exported Files', 'worddown')}</p>
            </div>
          </div>

          {/* Last Export */}
          <div className="relative group overflow-hidden flex items-center rounded-2xl border border-[#e1e5e9] bg-white px-6 py-7 shadow-[0_4px_12px_rgba(0,0,0,0.05)] transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] hover:-translate-y-1 hover:shadow-[0_12px_24px_rgba(0,0,0,0.12)] hover:border-[#c3c4c7]">
            <div className="absolute top-0 left-0 right-0 h-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300" style={{background: 'linear-gradient(90deg, #46b450 0%, #389a43 100%)'}} />
            <div className="stat-icon mr-5 rounded-xl p-4 shadow-[0_4px_12px_rgba(70,180,80,0.2)]" style={{background: 'linear-gradient(135deg, #46b450 0%, #389a43 100%)'}}>
              <Clock className="w-10 h-10 text-white" />
            </div>
            <div className="stat-content flex-1">
              <h3 className="!m-0 !mb-2 !text-[1.8em] font-bold leading-tight bg-gradient-to-r from-[#1d2327] to-[#50575e] bg-clip-text text-transparent">{lastExportDate ? lastExportDate.toLocaleDateString() : __('Never', 'worddown')}</h3>
              <p className="!m-0 text-[#646970] text-[0.95em] font-medium uppercase tracking-wide">{__('Last Export', 'worddown')}</p>
            </div>
          </div>

          {/* Auto Export */}
          <div className="relative group overflow-hidden flex items-center rounded-2xl border border-[#e1e5e9] bg-white px-6 py-7 shadow-[0_4px_12px_rgba(0,0,0,0.05)] transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] hover:-translate-y-1 hover:shadow-[0_12px_24px_rgba(0,0,0,0.12)] hover:border-[#c3c4c7]">
            <div className="absolute top-0 left-0 right-0 h-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300" style={{background: 'linear-gradient(90deg, #ff6b35 0%, #e55a2b 100%)'}} />
            <div className="stat-icon mr-5 rounded-xl p-4 shadow-[0_4px_12px_rgba(255,107,53,0.2)]" style={{background: 'linear-gradient(135deg, #ff6b35 0%, #e55a2b 100%)'}}>
              <SettingsIcon className="w-10 h-10 text-white" />
            </div>
            <div className="stat-content flex-1">
              <h3 className="!m-0 !mb-2 !text-[1.8em] font-bold leading-tight bg-gradient-to-r from-[#1d2327] to-[#50575e] bg-clip-text text-transparent">{data.auto_export ? __('Enabled', 'worddown') : __('Disabled', 'worddown')}</h3>
              <p className="!m-0 text-[#646970] text-[0.95em] font-medium uppercase tracking-wide">{__('Auto Export', 'worddown')}</p>
            </div>
          </div>
        </div>

        {/* Exported Content by Post Type Bar Chart */}
        {chartData.length > 0 && (
          <Card className="bg-white">
            <CardHeader>
              <CardTitle className="text-lg">{__('Exported files by Post Type', 'worddown')}</CardTitle>
            </CardHeader>
            <CardContent>
              <ChartContainer
                config={{
                  count: {
                    label: __('Exported files', 'worddown'),
                    color: '#2563eb',
                  },
                } satisfies ChartConfig}
                className="w-full"
                style={{ height: 350 }}
              >
                <BarChart
                  accessibilityLayer
                  data={chartData}
                  margin={{ top: 10, left: 0, right: 0, bottom: 0 }}
                  height={350}
                >
                  <YAxis
                    tick={{ fontSize: 13, fill: '#64748b' }}
                    axisLine={false}
                    tickLine={false}
                    width={48}
                    stroke="#e5e7eb"
                    allowDecimals={false}
                    domain={[0, maxValue]}
                    tickFormatter={v => v}
                    padding={{ top: 10, bottom: 8 }}
                    tickMargin={8}
                  />
                  <XAxis
                    dataKey="type"
                    tick={{ fontSize: 14, fill: '#64748b' }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <Bar dataKey="count" fill="var(--primary)" radius={4} barSize={60} >
                    <LabelList dataKey="count" position="top" fill="#64748b" fontSize={14} />
                  </Bar>
                </BarChart>
              </ChartContainer>
            </CardContent>
          </Card>
        )}

        {/* Export form card - use Card, white bg, default shadow */}
        <Card className="bg-white">
          <CardHeader>
            <CardTitle className="text-lg">{__('Export Now', 'worddown')}</CardTitle>
          </CardHeader>
          <CardContent className="px-6">
            <form onSubmit={handleExport} className="flex flex-col gap-4">
              <div className="flex flex-col md:flex-row md:items-center md:gap-4 gap-2 mb-2">
                {lastExportDate && (
                  <Alert className="w-full mb-2" variant="success">
                    <Clock />
                    <AlertTitle>{__('Last Export', 'worddown')}</AlertTitle>
                    <AlertDescription>
                      {lastExportDate.toLocaleString()} - {data.last_export?.count || 0} {__('files exported', 'worddown')}
                    </AlertDescription>
                  </Alert>
                )}
                {nextScheduledDate && (
                  <div className="text-sm text-muted-foreground">
                    <strong>{__('Next Scheduled Export:', 'worddown')}</strong>{' '}
                    {nextScheduledDate.toLocaleString()}
                  </div>
                )}
              </div>

              {exporting && exportStatus && (
                <div className="space-y-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                      <span className="text-sm font-medium text-blue-800">
                        {exportStatus.current_operation || __('Export in progress...', 'worddown')}
                      </span>
                    </div>
                    <span className="text-sm text-blue-600 font-medium">
                      {exportStatus.progress_percentage?.toFixed(1)}%
                    </span>
                  </div>
                  
                  <Progress value={progress} className="w-full" />
                  
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 text-sm">
                    <div className="text-center bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
                      <div className="font-semibold text-blue-800 text-lg">{exportStatus.total_posts}</div>
                      <div className="text-blue-600 text-xs uppercase tracking-wide">{__('Total Posts', 'worddown')}</div>
                    </div>
                    <div className="text-center bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
                      <div className="font-semibold text-green-800 text-lg">{exportStatus.exported || 0}</div>
                      <div className="text-green-600 text-xs uppercase tracking-wide">{__('Exported', 'worddown')}</div>
                    </div>
                    <div className="text-center bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
                      <div className="font-semibold text-red-800 text-lg">{exportStatus.failed || 0}</div>
                      <div className="text-red-600 text-xs uppercase tracking-wide">{__('Failed', 'worddown')}</div>
                    </div>
                  </div>
                  
                  {exportStatus.estimated_completion && (
                    <div className="text-center text-sm text-blue-600">
                      {__('Estimated completion:', 'worddown')} {new Date(exportStatus.estimated_completion * 1000).toLocaleTimeString()}
                    </div>
                  )}
                </div>
              )}

              <div className="flex flex-col md:flex-row md:items-center md:gap-6 gap-2 mt-2">
                {!exporting ? (
                  <Button type="submit" size="lg" className="px-8 py-3 text-base font-semibold" id="worddown-export-button">
                    {__('Export Now', 'worddown')}
                  </Button>
                ) : (
                  <Button 
                    type="button" 
                    size="lg" 
                    variant="destructive"
                    className="px-8 py-3 text-base font-semibold" 
                    onClick={handleCancelExport}
                  >
                    {__('Cancel Export', 'worddown')}
                  </Button>
                )}

                <p className="description mt-2 text-xs text-muted-foreground md:mt-0">
                  {exporting 
                    ? __('Export is running in the background. You can safely close this page.', 'worddown')
                    : __('Start a background export of all content based on your current settings.', 'worddown')
                  }
                </p>
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Static export info card - use Card, white bg, default shadow */}
        <Card className="bg-white">
          <CardHeader>
            <CardTitle className="text-lg">{__('Export Information', 'worddown')}</CardTitle>
          </CardHeader>

          <CardContent>
            <div className="mb-3 flex flex-col gap-2">
              <strong>{__('Export Directory:', 'worddown')}</strong>
              <code className="bg-[#f3f4f6] px-2 py-1 rounded text-sm">{data.export_dir}</code>
            </div>

            <div className="mb-3 flex items-center gap-2">
              {data.export_dir && data.exported_files_count >= 0 ? (
                <CheckCircle2 className="w-5 h-5 text-green-600" />
              ) : (
                <AlertTriangle className="w-5 h-5 text-red-600" />
              )}
              <span className="text-sm">
                {data.export_dir && data.exported_files_count >= 0
                  ? __('Directory exists and is writable', 'worddown')
                  : __('Directory does not exist or is not writable', 'worddown')}
              </span>
            </div>

            <div className="mb-3 flex items-center gap-2">
              <strong>{__('File Format:', 'worddown')}</strong>
              <code className="bg-[#f3f4f6] px-2 py-1 rounded text-sm">{'{post-type}-{title}-{id}.md'}</code>
            </div>
            {/* Content Statistics removed */}
            <Toaster position="bottom-right" richColors />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}