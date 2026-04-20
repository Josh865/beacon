import { Head, Link, useForm } from "@inertiajs/react";
import {
  CalendarClock,
  CheckCircle2,
  Clock3,
  LoaderCircle,
  MessageSquareText,
  Send,
  Users,
  XCircle,
} from "lucide-react";
import { useState } from "react";

import CampaignController from "@/actions/App/Http/Controllers/CampaignController";
import AppPagination, { type PaginationData } from "@/components/app-pagination";
import CampaignStatusBadge from "@/components/campaigns/campaign-status-badge";
import Heading from "@/components/heading";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { index as campaignsIndex } from "@/routes/campaigns";

type CampaignShowProps = {
  campaign: {
    id: number;
    name: string;
    message_body: string;
    status: "draft" | "scheduled" | "processing" | "sent" | "cancelled";
    status_label: string;
    audience_type: "all_contacts" | "tag_selection" | "manual_selection";
    audience_label: string;
    scheduled_for: string | null;
    sent_at: string | null;
    recipient_count: number;
    delivery_counts: {
      pending: number;
      sent: number;
      failed: number;
      skipped: number;
    };
    created_at: string;
    updated_at: string;
    can_edit: boolean;
    can_schedule: boolean;
    tags: Array<{
      id: number;
      name: string;
      slug: string;
    }>;
  };
  recipients: PaginationData & {
    data: Array<{
      id: number;
      contact: {
        id: number;
        name: string;
        phone: string;
      } | null;
      delivery_status: "pending" | "sent" | "failed" | "skipped";
      delivery_error: string | null;
      processed_at: string | null;
    }>;
  };
  audiencePreview: {
    count: number;
    audience_type: "all_contacts" | "tag_selection" | "manual_selection";
    audience_label: string;
    implemented: boolean;
  };
};

type ScheduleCampaignForm = {
  scheduled_for: string;
};

type DeliveryStatus = "pending" | "sent" | "failed" | "skipped";

const deliveryStatusBadgeClasses: Record<DeliveryStatus, string> = {
  pending: "bg-amber-500 text-amber-950 hover:bg-amber-500",
  sent: "bg-emerald-600 text-white hover:bg-emerald-600",
  failed: "bg-rose-600 text-white hover:bg-rose-600",
  skipped: "bg-slate-200 text-slate-700 hover:bg-slate-200",
};

const deliveryStatusLabels: Record<DeliveryStatus, string> = {
  pending: "Pending",
  sent: "Sent",
  failed: "Failed",
  skipped: "Skipped",
};

function formatDateTime(value: string | null, fallback = "Not available"): string {
  if (!value) {
    return fallback;
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function formatForDateTimeLocal(value: string): string {
  const date = new Date(value);
  const timezoneOffset = date.getTimezoneOffset() * 60_000;

  return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

function nextHourDateTimeLocal(): string {
  const date = new Date();

  date.setMinutes(0, 0, 0);
  date.setHours(date.getHours() + 1);

  return formatForDateTimeLocal(date.toISOString());
}

export default function ShowCampaign({ campaign, recipients, audiencePreview }: CampaignShowProps) {
  const initialScheduledForInput = campaign.scheduled_for
    ? formatForDateTimeLocal(campaign.scheduled_for)
    : nextHourDateTimeLocal();
  const form = useForm<ScheduleCampaignForm>({
    scheduled_for: new Date(initialScheduledForInput).toISOString(),
  });
  const [scheduledForInput, setScheduledForInput] = useState<string>(initialScheduledForInput);
  const [confirmed, setConfirmed] = useState(false);
  const isDraft = campaign.can_schedule;
  const canSchedule =
    isDraft && audiencePreview.implemented && audiencePreview.count > 0 && confirmed;
  const scheduleErrors = form.errors as Record<string, string | undefined>;

  const submitSchedule = () => {
    form.post(CampaignController.schedule(campaign.id).url, {
      preserveScroll: true,
    });
  };

  const updateScheduledFor = (value: string) => {
    setScheduledForInput(value);
    form.setData("scheduled_for", value ? new Date(value).toISOString() : "");
  };

  return (
    <>
      <Head title={campaign.name} />

      <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <Heading
            title={campaign.name}
            description="Review campaign status, recipient delivery progress, and the locked audience snapshot."
          />

          <div className="flex gap-3">
            <Link
              href={campaignsIndex()}
              preserveScroll
              prefetch
              className={buttonVariants({ variant: "outline" })}
            >
              Back
            </Link>
            {campaign.can_edit ? (
              <Link href={CampaignController.edit(campaign.id)} className={buttonVariants()}>
                Edit draft
              </Link>
            ) : null}
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Card className="border-border/70 border shadow-sm">
            <CardHeader className="pb-3">
              <CardDescription>Campaign status</CardDescription>
              <div className="flex items-center gap-2">
                <CardTitle className="text-xl">{campaign.status_label}</CardTitle>
                <CampaignStatusBadge status={campaign.status} />
              </div>
            </CardHeader>
            <CardContent className="text-muted-foreground text-sm">
              Scheduled for {formatDateTime(campaign.scheduled_for, "Not scheduled")}
            </CardContent>
          </Card>

          <Card className="border-border/70 border shadow-sm">
            <CardHeader className="pb-3">
              <CardDescription>Recipients</CardDescription>
              <CardTitle className="text-xl">{campaign.recipient_count}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground text-sm">
              Total snapshotted contacts attached to this campaign.
            </CardContent>
          </Card>

          <Card className="border-border/70 border shadow-sm">
            <CardHeader className="pb-3">
              <CardDescription>Delivered</CardDescription>
              <CardTitle className="text-xl">{campaign.delivery_counts.sent}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground text-sm">
              Failed: {campaign.delivery_counts.failed} • Pending:{" "}
              {campaign.delivery_counts.pending}
            </CardContent>
          </Card>

          <Card className="border-border/70 border shadow-sm">
            <CardHeader className="pb-3">
              <CardDescription>Sent at</CardDescription>
              <CardTitle className="text-xl">
                {campaign.sent_at ? formatDateTime(campaign.sent_at) : "Not sent yet"}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground text-sm">
              The campaign is marked sent after all recipients are processed.
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
          <div className="flex flex-col gap-6">
            <Card className="border-border/70 border shadow-sm">
              <CardHeader>
                <div className="flex flex-wrap items-center gap-3">
                  <CardTitle>Message</CardTitle>
                  <CampaignStatusBadge status={campaign.status} />
                </div>
                <CardDescription>
                  This saved message belongs only to your account and is processed against this
                  campaign&apos;s recipient snapshot.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <div className="bg-muted/20 rounded-2xl border px-4 py-4 text-sm leading-7 whitespace-pre-wrap">
                  {campaign.message_body}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/70 border shadow-sm">
              <CardHeader>
                <CardTitle>Delivery overview</CardTitle>
                <CardDescription>
                  Per-recipient delivery outcomes are stored even when some simulated sends fail.
                </CardDescription>
              </CardHeader>

              <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="bg-muted/25 rounded-2xl border px-4 py-4">
                  <div className="flex items-center gap-2">
                    <Send className="size-4 text-emerald-600" />
                    <p className="text-sm font-medium">Sent</p>
                  </div>
                  <p className="mt-3 text-3xl font-semibold">{campaign.delivery_counts.sent}</p>
                </div>

                <div className="bg-muted/25 rounded-2xl border px-4 py-4">
                  <div className="flex items-center gap-2">
                    <Clock3 className="size-4 text-amber-600" />
                    <p className="text-sm font-medium">Pending</p>
                  </div>
                  <p className="mt-3 text-3xl font-semibold">{campaign.delivery_counts.pending}</p>
                </div>

                <div className="bg-muted/25 rounded-2xl border px-4 py-4">
                  <div className="flex items-center gap-2">
                    <XCircle className="size-4 text-rose-600" />
                    <p className="text-sm font-medium">Failed</p>
                  </div>
                  <p className="mt-3 text-3xl font-semibold">{campaign.delivery_counts.failed}</p>
                </div>

                <div className="bg-muted/25 rounded-2xl border px-4 py-4">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="size-4 text-slate-600" />
                    <p className="text-sm font-medium">Skipped</p>
                  </div>
                  <p className="mt-3 text-3xl font-semibold">{campaign.delivery_counts.skipped}</p>
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/70 border shadow-sm">
              <CardHeader>
                <CardTitle>Recipient delivery log</CardTitle>
                <CardDescription>
                  This table is limited to recipients snapshotted onto this campaign.
                </CardDescription>
              </CardHeader>

              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Recipient</TableHead>
                      <TableHead>Phone</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Processed</TableHead>
                      <TableHead>Error</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recipients.data.length > 0 ? (
                      recipients.data.map((recipient) => (
                        <TableRow key={recipient.id}>
                          <TableCell>{recipient.contact?.name ?? "Contact unavailable"}</TableCell>
                          <TableCell>{recipient.contact?.phone ?? "Unavailable"}</TableCell>
                          <TableCell>
                            <Badge
                              className={deliveryStatusBadgeClasses[recipient.delivery_status]}
                            >
                              {deliveryStatusLabels[recipient.delivery_status]}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            {formatDateTime(recipient.processed_at, "Not processed")}
                          </TableCell>
                          <TableCell>{recipient.delivery_error ?? "—"}</TableCell>
                        </TableRow>
                      ))
                    ) : (
                      <TableRow>
                        <TableCell colSpan={5}>No recipient snapshot is available yet.</TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </CardContent>

              <CardContent className="px-0">
                <AppPagination {...recipients} itemLabel="recipients" className="px-6" />
              </CardContent>
            </Card>
          </div>

          <div className="flex flex-col gap-6">
            <Card className="border-border/70 border shadow-sm">
              <CardHeader>
                <CardTitle>Audience summary</CardTitle>
                <CardDescription>Preview is scoped to your active contacts only.</CardDescription>
              </CardHeader>

              <CardContent className="grid gap-4">
                <div className="flex items-start gap-3">
                  <Users className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                  <div className="space-y-1">
                    <p className="font-medium">{campaign.audience_label}</p>
                    <p className="text-muted-foreground text-sm">
                      {audiencePreview.implemented
                        ? `${audiencePreview.count} active contacts currently match this audience.`
                        : "Manual selection preview is not implemented yet."}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <CalendarClock className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                  <div className="space-y-1">
                    <p className="font-medium">Scheduling</p>
                    <p className="text-muted-foreground text-sm">
                      {campaign.scheduled_for
                        ? `Scheduled for ${formatDateTime(campaign.scheduled_for)}.`
                        : "This campaign is still a draft and has not been scheduled yet."}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <Send className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                  <div className="space-y-1">
                    <p className="font-medium">Delivery completion</p>
                    <p className="text-muted-foreground text-sm">
                      {campaign.sent_at
                        ? `Marked sent at ${formatDateTime(campaign.sent_at)}.`
                        : "This campaign has not completed processing yet."}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-3">
                  <MessageSquareText className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                  <div className="space-y-1">
                    <p className="font-medium">Tags</p>
                    <div className="flex flex-wrap gap-1.5">
                      {campaign.tags.length > 0 ? (
                        campaign.tags.map((tag) => (
                          <Badge key={tag.id} variant="outline" className="bg-muted/30">
                            {tag.name}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-muted-foreground text-sm">No tags selected</span>
                      )}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/70 border shadow-sm">
              <CardHeader>
                <CardTitle>{isDraft ? "Schedule campaign" : "Schedule details"}</CardTitle>
                <CardDescription>
                  {isDraft
                    ? "Scheduling freezes the matching recipients at that moment and queues the campaign for processing."
                    : "This campaign is no longer a draft, so scheduling is locked."}
                </CardDescription>
              </CardHeader>

              <CardContent className="grid gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="scheduled_for">Send date and time</Label>
                  <Input
                    id="scheduled_for"
                    type="datetime-local"
                    value={scheduledForInput}
                    onChange={(event) => updateScheduledFor(event.target.value)}
                    disabled={!isDraft || form.processing}
                  />
                  <InputError message={form.errors.scheduled_for} />
                </div>

                <div className="bg-muted/30 rounded-2xl px-4 py-4">
                  <p className="text-muted-foreground text-xs tracking-[0.18em] uppercase">
                    Snapshot impact
                  </p>
                  <p className="mt-2 text-sm leading-6">
                    {audiencePreview.implemented
                      ? `If you schedule right now, ${audiencePreview.count} active contacts would be snapshotted into this campaign.`
                      : "Manual selection is not available for scheduling yet."}
                  </p>
                </div>

                {isDraft ? (
                  <label
                    htmlFor="confirm_schedule"
                    className="border-border/70 bg-background/80 flex items-start gap-3 rounded-xl border px-3 py-3"
                  >
                    <Checkbox
                      id="confirm_schedule"
                      checked={confirmed}
                      onCheckedChange={(checked) => setConfirmed(Boolean(checked))}
                    />
                    <div className="space-y-1 text-sm">
                      <p className="font-medium">Confirm recipient snapshot</p>
                      <p className="text-muted-foreground">
                        I understand this captures the matching recipients at scheduling time and
                        moves the campaign out of draft.
                      </p>
                    </div>
                  </label>
                ) : null}

                <InputError message={scheduleErrors.audience || scheduleErrors.campaign} />
              </CardContent>

              <CardContent className="pt-0">
                <Button disabled={!canSchedule || form.processing} onClick={submitSchedule}>
                  {form.processing ? <LoaderCircle className="animate-spin" /> : null}
                  {isDraft ? "Schedule campaign" : "Scheduling locked"}
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </>
  );
}

ShowCampaign.layout = {
  breadcrumbs: [
    {
      title: "Campaigns",
      href: campaignsIndex(),
    },
  ],
};
