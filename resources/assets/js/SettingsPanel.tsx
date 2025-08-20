import React, { useEffect, useState } from 'react';

import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Toaster } from "@/components/ui/sonner"
import { toast } from "sonner"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { InfoIcon } from "lucide-react";

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

import {
  Card,
  CardContent,
  CardFooter,
} from "@/components/ui/card"

import { Skeleton } from "@/components/ui/skeleton"
import { Settings as SettingsIcon, Key as KeyIcon, CalendarSync as CalendarSyncIcon } from "lucide-react";
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter';
import { oneDark } from 'react-syntax-highlighter/dist/esm/styles/prism';

interface Field {
  key: string;
  type: string;
  label: string;
  description?: string;
  default?: any;
  options?: { value: string; label: string }[];
  switch_label?: string;
  min?: number;
  max?: number;
  step?: number;
}

interface Section {
  title?: string;
  description?: string;
  fields?: Field[];
  content?: any[];
}

interface Tab {
  key: string;
  label: string;
  icon?: string;
  sections?: Section[];
}

interface Schema {
  tabs: Tab[];
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

export default function SettingsPanel() {
  const [schema, setSchema] = useState<Schema | null>(null);
  const [settings, setSettings] = useState<Record<string, any> | null>(null);
  const [activeTab, setActiveTab] = useState<string>('general');
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [postTypeOptions, setPostTypeOptions] = useState<{ value: string; label: string }[]>([]);
  const [adapters, setAdapters] = useState<{ slug: string; label: string; description?: string }[] | null>(null);

  useEffect(() => {
    async function fetchData() {
      try {
        const [schemaRes, settingsRes, postTypesRes, adaptersRes] = await Promise.all([
          fetch(`${API_BASE}/settings-schema`, {
            headers: { 'X-WP-Nonce': REST_NONCE }
          }).then(r => r.json()),
          fetch(`${API_BASE}/settings`, {
            headers: { 'X-WP-Nonce': REST_NONCE }
          }).then(r => r.json()),
          fetch(`${API_BASE}/post-types`, {
            headers: { 'X-WP-Nonce': REST_NONCE }
          }).then(r => r.json()),
          fetch(`${API_BASE}/adapters`, {
            headers: { 'X-WP-Nonce': REST_NONCE }
          }).then(r => r.json()),
        ]);
        setSchema(schemaRes);
        setSettings(settingsRes);
        setPostTypeOptions(postTypesRes);
        setAdapters(adaptersRes);
        setLoading(false);
      } catch (e) {
        toast.error(__('Failed to save settings.', 'worddown'));
        setLoading(false);
      }
    }
    fetchData();
  }, []);

  function handleChange(key: string, value: any) {
    setSettings(prev => ({ ...(prev || {}), [key]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);

    try {
      const res = await fetch(`${API_BASE}/settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': REST_NONCE,
        } as HeadersInit,
        body: JSON.stringify(settings),
      });
      
      const data = await res.json();

      if (data.success) {
        toast.success(__('Settings saved!', 'worddown'));
      } else {
        if (data.error) {
          toast.error(data.error);
        } else {
          toast.error(__('Failed to save settings.', 'worddown'));
        }
      }
    } catch (e) {
      toast.error(__('Failed to save settings.', 'worddown'));
    }

    setSaving(false);
  }

  if (loading) return (
    <div className="worddown-settings-panel mt-4">
      <form className="max-w-5xl space-y-0">
        <Card>
          <div className="px-6">
            {/* Section 1 */}
            <Skeleton className="h-12 w-1/3 mb-6" /> {/* Tabs */}
            <div className="space-y-7">
              {[...Array(3)].map((_, i) => (
                <div key={i} className="flex items-center gap-8">
                  <Skeleton className="h-5 w-[200px]" /> {/* Label */}
                  <div className="flex-1">
                    {i === 0 ? (
                      // Switch skeleton
                      <div className="flex items-center gap-3">
                        <Skeleton className="h-6 w-12 rounded-full" />
                        <Skeleton className="h-4 w-32" />
                      </div>
                    ) : i === 1 ? (
                      // Select skeleton
                      <Skeleton className="h-9 w-full rounded-md" />
                    ) : (
                      // Time input skeleton
                      <Skeleton className="h-9 w-32 rounded-md" />
                    )}
                    <Skeleton className="h-3 w-1/2 mt-2" /> {/* Description */}
                  </div>
                </div>
              ))}
            </div>
            <Skeleton className="h-px w-full my-8" /> {/* Divider */}
            {/* Section 2 */}
            <Skeleton className="h-6 w-1/4 mb-6" /> {/* Section Title */}
            <div className="space-y-7">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="flex items-center gap-8">
                  <Skeleton className="h-5 w-[200px]" /> {/* Label */}
                  <div className="flex-1">
                    {i === 0 ? (
                      // Post types switches
                      <div className="space-y-2">
                        {[...Array(3)].map((_, j) => (
                          <div key={j} className="flex items-center gap-3 mb-1">
                            <Skeleton className="h-6 w-12 rounded-full" />
                            <Skeleton className="h-4 w-24" />
                          </div>
                        ))}
                      </div>
                    ) : (
                      // Switch skeleton
                      <div className="flex items-center gap-3">
                        <Skeleton className="h-6 w-12 rounded-full" />
                        <Skeleton className="h-4 w-32" />
                      </div>
                    )}
                    <Skeleton className="h-3 w-1/2 mt-2" /> {/* Description */}
                  </div>
                </div>
              ))}
            </div>
            <div className="flex justify-start mt-10">
              <Skeleton className="h-10 w-32 rounded-md" /> {/* Save button */}
            </div>
          </div>
        </Card>
      </form>
    </div>
  );

  if (!schema || !Array.isArray(schema.tabs) || !settings) return null;

  return (
    <div className="worddown-settings-panel mt-4">
      <form onSubmit={handleSave} className="max-w-5xl">
        <Card>
          <CardContent>
            <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
              <TabsList className="mb-3">
                {schema.tabs.map(tab => {
                  let Icon = SettingsIcon;
                  if (tab.key === 'api') {
                    Icon = KeyIcon;
                  }
                  if (tab.key === 'schedule') {
                    Icon = CalendarSyncIcon;
                  }
                  return (
                    <TabsTrigger
                      key={tab.key}
                      value={tab.key}
                      className="flex items-center gap-2 px-6 py-3 text-base"
                    >
                      <Icon className="h-5 w-5 text-muted-foreground" />
                      {tab.label}
                    </TabsTrigger>
                  );
                })}
              </TabsList>
              {schema.tabs.map(tab => (
                <TabsContent key={tab.key} value={tab.key} className="w-full space-y-6">
                  {tab.sections && tab.sections.map((section, sidx) => (
                    <React.Fragment key={sidx}>
                      {section.title && (
                        <div className="text-lg font-semibold">{section.title}</div>
                      )}

                      {section.description && (
                        <div className="text-sm text-muted-foreground mb-4">{section.description}</div>
                      )}

                      {section.fields && (
                        <div className="space-y-7">
                          {section.fields.map(field => {
                            if (field.type === 'adapters') {
                              if (!adapters) {
                                return (
                                  <div key={field.key} className="flex flex-col gap-6">
                                    <Skeleton className="h-6 w-40 mb-2 rounded" />
                                    <Skeleton className="h-6 w-40 mb-2 rounded" />
                                  </div>
                                );
                              }
                              return (
                                <div key={field.key} className="flex flex-col gap-6">
                                  {(!adapters || adapters.length === 0) ? (
                                    <Alert variant="info" className="mb-4">
                                      <InfoIcon />
                                      <AlertDescription>
                                        {__('Supported adapters will be shown here automatically when they are available.', 'worddown')}
                                      </AlertDescription>
                                    </Alert>
                                  ) : (
                                    adapters.map(adapter => (
                                      <div key={adapter.slug} className="flex flex-col gap-3 md:flex-row md:items-start md:gap-8">
                                        <label className="block font-medium min-w-[200px] text-base">
                                          {adapter.label}
                                        </label>
                                        <div className="flex-1">
                                          <div className="flex items-center gap-2">
                                            <Switch
                                              checked={!!settings[`include_${adapter.slug}`]}
                                              onCheckedChange={val => handleChange(`include_${adapter.slug}`, val)}
                                              id={`switch-include-${adapter.slug}`}
                                            />
                                            {adapter.description && (
                                              <label htmlFor={`switch-include-${adapter.slug}`} className="text-sm font-normal cursor-pointer select-none">{adapter.description}</label>
                                            )}
                                          </div>
                                        </div>
                                      </div>
                                    ))
                                  )}
                                </div>
                              );
                            }
                            return (
                              <div key={field.key} className="flex flex-col gap-3 md:flex-row md:items-start md:gap-8">
                                <label className="block font-medium min-w-[200px] text-base">
                                  {field.label}
                                </label>
                                <div className="flex-1">
                                  {/* Render field based on type */}
                                  {field.type === 'boolean' && (
                                    <div className="flex items-center gap-2">
                                      <Switch
                                        checked={!!settings[field.key]}
                                        onCheckedChange={val => handleChange(field.key, val)}
                                        id={`switch-${field.key}`}
                                      />

                                      <label htmlFor={`switch-${field.key}`} className="text-sm font-normal cursor-pointer select-none">
                                        {field.switch_label || field.label}
                                      </label>
                                    </div>
                                  )}
                                  {field.type === 'text' && (
                                    <Input
                                      type="text"
                                      value={settings[field.key] || ''}
                                      onChange={e => handleChange(field.key, e.target.value)}
                                    />
                                  )}
                                  {field.type === 'number' && (
                                    <Input
                                      type="number"
                                      min={field.min}
                                      max={field.max}
                                      step={field.step}
                                      value={settings[field.key] !== undefined ? settings[field.key] : field.default || 0}
                                      onChange={e => {
                                        const value = e.target.value;
                                        if (value === '') {
                                          handleChange(field.key, field.default || 0);
                                        } else {
                                          const numValue = parseInt(value, 10);
                                          if (!isNaN(numValue)) {
                                            handleChange(field.key, numValue);
                                          }
                                        }
                                      }}
                                      className="w-32"
                                    />
                                  )}
                                  {field.type === 'time' && (
                                    <Input
                                      type="time"
                                      id={`time-picker-${field.key}`}
                                      step="1"
                                      value={settings[field.key] || ''}
                                      onChange={e => handleChange(field.key, e.target.value)}
                                      className="bg-background appearance-none [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
                                    />
                                  )}
                                  {field.type === 'select' && (
                                    <Select
                                      value={settings[field.key] || field.default}
                                      onValueChange={val => handleChange(field.key, val)}
                                      options={field.options || []}
                                    >
                                      <SelectTrigger className="w-full">
                                        <SelectValue placeholder={field.label} />
                                      </SelectTrigger>
                                      <SelectContent>
                                        {field.options && field.options.map(opt => (
                                          <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                          </SelectItem>
                                        ))}
                                      </SelectContent>
                                    </Select>
                                  )}
                                  {field.type === 'post_types' && (
                                    <div className="space-y-2">
                                      {postTypeOptions.map(opt => {
                                        const checked = Array.isArray(settings[field.key]) && settings[field.key].includes(opt.value);
                                        return (
                                          <div key={opt.value} className="flex items-center gap-2">
                                            <Switch
                                              checked={checked}
                                              onCheckedChange={val => {
                                                setSettings(prev => {
                                                  const safePrev = prev || {};
                                                  const current = Array.isArray(safePrev[field.key]) ? safePrev[field.key] : [];
                                                  return {
                                                    ...safePrev,
                                                    [field.key]: val
                                                      ? [...current, opt.value]
                                                      : current.filter((v: string) => v !== opt.value)
                                                  };
                                                });
                                              }}
                                              id={`switch-${field.key}-${opt.value}`}
                                            />
                                            <label htmlFor={`switch-${field.key}-${opt.value}`} className="text-sm cursor-pointer font-normal select-none">
                                              {opt.label}
                                            </label>
                                          </div>
                                        );
                                      })}
                                    </div>
                                  )}
                                  {field.description && (
                                    <div className="text-xs text-muted-foreground mt-3">{field.description}</div>
                                  )}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      )}
                      {(section.content && section.content.length > 0) && (
                        <div className="flex flex-col items-start gap-4">
                          {section.content.map((item, i) => {
                            if (item.type === 'endpoint') {
                              return (
                                <div key={i} className="flex flex-col gap-2 p-4 border rounded-lg bg-transparent mb-2 w-full">
                                  <div className="flex items-start gap-2 mb-1">
                                    <span className="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-primary text-primary-foreground uppercase tracking-wide">
                                      {item.method}
                                    </span>
                                    <div>
                                      <span className="font-mono bg-muted px-2 py-1 rounded text-xs text-foreground select-all">
                                        {item.url}
                                      </span>

                                      <div className="text-xs text-muted-foreground mt-3">{item.desc}</div>
                                    </div>
                                  </div>
                                </div>
                              );
                            }
                            if (item.type === 'code') {
                              return (
                                <div key={i} className="dark w-full">
                                  <SyntaxHighlighter
                                    language={item.language || 'bash'}
                                    style={oneDark}
                                    customStyle={{ borderRadius: '0.5rem', fontSize: '0.85rem', padding: '1rem', background: 'rgb(40, 44, 52)' }}
                                    showLineNumbers={false}
                                  >
                                    {item.code}
                                  </SyntaxHighlighter>
                                </div>
                              );
                            }
                            return null;
                          })}
                        </div>
                      )}

                      {tab.sections && sidx < tab.sections.length - 1 && (
                        <hr className="my-5 border-t border-gray-200" />
                      )}
                    </React.Fragment>
                  ))}
                </TabsContent>
              ))}
            </Tabs>
          </CardContent>

          <CardFooter>
            <Button type="submit" disabled={saving}>
              {saving ? __('Saving...', 'worddown') : __('Save Settings', 'worddown')}
            </Button>

            <Toaster position="bottom-right" richColors />
          </CardFooter>
        </Card>
      </form>
    </div>
  );
}