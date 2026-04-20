import { Link, useForm } from "@inertiajs/react";
import { LoaderCircle, Sparkles } from "lucide-react";
import { useState } from "react";

import CampaignController from "@/actions/App/Http/Controllers/CampaignController";
import Heading from "@/components/heading";
import InputError from "@/components/input-error";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type AudienceTypeValue = "all_contacts" | "tag_selection" | "manual_selection";

type TagOption = {
  id: number;
  name: string;
  slug: string;
};

type AudienceTypeOption = {
  value: AudienceTypeValue;
  label: string;
  implemented: boolean;
};

export type CampaignAudiencePreview = {
  count: number;
  audience_type: AudienceTypeValue;
  audience_label: string;
  implemented: boolean;
};

export type CampaignFormValues = {
  name: string;
  message_body: string;
  status: "draft";
  audience_type: AudienceTypeValue;
  tag_ids: number[];
};

type CampaignFormProps = {
  title: string;
  description: string;
  campaign: CampaignFormValues;
  tags: TagOption[];
  audienceTypes: AudienceTypeOption[];
  audiencePreview: CampaignAudiencePreview;
  submitLabel: string;
  cancelHref: string;
  submitAction:
    | ReturnType<typeof CampaignController.store>
    | ReturnType<typeof CampaignController.update>;
};

function getXsrfToken(): string {
  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.split("=")[1];

  return token ? decodeURIComponent(token) : "";
}

function normalizeTagIds(tagIds: number[]): string {
  return [...tagIds].sort((left, right) => left - right).join(",");
}

export default function CampaignForm({
  title,
  description,
  campaign,
  tags,
  audienceTypes,
  audiencePreview: initialAudiencePreview,
  submitLabel,
  cancelHref,
  submitAction,
}: CampaignFormProps) {
  const form = useForm<CampaignFormValues>(campaign);
  const [audiencePreview, setAudiencePreview] =
    useState<CampaignAudiencePreview>(initialAudiencePreview);
  const [previewAudienceType, setPreviewAudienceType] = useState<AudienceTypeValue>(
    initialAudiencePreview.audience_type,
  );
  const [previewTagIds, setPreviewTagIds] = useState<number[]>(campaign.tag_ids);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [previewProcessing, setPreviewProcessing] = useState(false);

  const previewIsStale =
    previewAudienceType !== form.data.audience_type ||
    normalizeTagIds(previewTagIds) !== normalizeTagIds(form.data.tag_ids);

  const selectedAudienceType = audienceTypes.find(
    (audienceType) => audienceType.value === form.data.audience_type,
  );

  const toggleTag = (tagId: number, checked: boolean) => {
    form.setData(
      "tag_ids",
      checked
        ? [...form.data.tag_ids, tagId].sort((left, right) => left - right)
        : form.data.tag_ids.filter((currentTagId) => currentTagId !== tagId),
    );
  };

  const refreshPreview = async () => {
    if (form.data.audience_type === "tag_selection" && form.data.tag_ids.length === 0) {
      setPreviewError("Select at least one tag to preview this audience.");

      return;
    }

    setPreviewProcessing(true);
    setPreviewError(null);

    try {
      const response = await fetch(CampaignController.previewAudience().url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-XSRF-TOKEN": getXsrfToken(),
        },
        body: JSON.stringify({
          audience_type: form.data.audience_type,
          tag_ids: form.data.tag_ids,
        }),
      });

      const payload = (await response.json()) as
        | CampaignAudiencePreview
        | { errors?: Record<string, string[]> };

      if (!response.ok) {
        const validationErrors = "errors" in payload ? payload.errors : undefined;
        const firstError = validationErrors?.tag_ids?.[0] ?? validationErrors?.audience_type?.[0];

        setPreviewError(firstError ?? "Unable to preview this audience right now.");

        return;
      }

      setAudiencePreview(payload as CampaignAudiencePreview);
      setPreviewAudienceType(form.data.audience_type);
      setPreviewTagIds(form.data.tag_ids);
    } catch {
      setPreviewError("Unable to preview this audience right now.");
    } finally {
      setPreviewProcessing(false);
    }
  };

  const submit = () => {
    form.submit(submitAction, {
      preserveScroll: true,
    });
  };

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
      <Heading title={title} description={description} />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <Card className="border-border/70 border shadow-sm">
          <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <CardTitle>Campaign draft</CardTitle>
              <CardDescription>
                Shape the message and audience before you worry about scheduling.
              </CardDescription>
            </div>

            <Badge className="bg-sky-600 text-white hover:bg-sky-600">Draft only</Badge>
          </CardHeader>

          <CardContent className="grid gap-5">
            <div className="grid gap-2">
              <Label htmlFor="name">Campaign name</Label>
              <Input
                id="name"
                value={form.data.name}
                onChange={(event) => form.setData("name", event.target.value)}
                placeholder="Midweek encouragement"
              />
              <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
              <div className="flex items-center justify-between gap-3">
                <Label htmlFor="message_body">Message body</Label>
                <span className="text-muted-foreground text-xs">
                  {form.data.message_body.length}/2000
                </span>
              </div>
              <textarea
                id="message_body"
                value={form.data.message_body}
                onChange={(event) => form.setData("message_body", event.target.value)}
                rows={12}
                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 min-h-56 rounded-xl border bg-transparent px-3 py-2 text-sm leading-6 transition-colors outline-none focus-visible:ring-3"
                placeholder="Hi church family, this Wednesday we are gathering for prayer at 6:30 PM in the fellowship hall..."
              />
              <InputError message={form.errors.message_body} />
            </div>
          </CardContent>
        </Card>

        <div className="flex flex-col gap-6">
          <Card className="border-border/70 border shadow-sm">
            <CardHeader>
              <CardTitle>Audience</CardTitle>
              <CardDescription>
                Choose who should receive this draft once sending is enabled.
              </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-5">
              <div className="grid gap-2">
                <Label htmlFor="audience_type">Audience type</Label>
                <Select
                  value={form.data.audience_type}
                  onValueChange={(value) =>
                    form.setData("audience_type", value as CampaignFormValues["audience_type"])
                  }
                >
                  <SelectTrigger id="audience_type" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {audienceTypes.map((audienceType) => (
                      <SelectItem
                        key={audienceType.value}
                        value={audienceType.value}
                        disabled={!audienceType.implemented}
                      >
                        {audienceType.label}
                        {!audienceType.implemented ? " (coming soon)" : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={form.errors.audience_type} />
              </div>

              {form.data.audience_type === "tag_selection" ? (
                <div className="grid gap-3">
                  <div className="flex items-center justify-between gap-3">
                    <Label>Selected tags</Label>
                    <span className="text-muted-foreground text-xs">
                      {form.data.tag_ids.length} chosen
                    </span>
                  </div>

                  {tags.length > 0 ? (
                    <div className="grid gap-2">
                      {tags.map((tag) => (
                        <label
                          key={tag.id}
                          htmlFor={`tag-${tag.id}`}
                          className="border-border/70 bg-background/80 hover:bg-muted/40 flex items-start gap-3 rounded-xl border px-3 py-3 transition-colors"
                        >
                          <Checkbox
                            id={`tag-${tag.id}`}
                            checked={form.data.tag_ids.includes(tag.id)}
                            onCheckedChange={(checked) => toggleTag(tag.id, Boolean(checked))}
                          />
                          <div className="space-y-1">
                            <p className="text-sm font-medium">{tag.name}</p>
                            <p className="text-muted-foreground text-xs">{tag.slug}</p>
                          </div>
                        </label>
                      ))}
                    </div>
                  ) : (
                    <div className="border-border/70 bg-muted/20 rounded-xl border px-4 py-4 text-sm">
                      No tags are available yet. Create tags first to build a segmented audience.
                    </div>
                  )}

                  <InputError message={form.errors.tag_ids || form.errors["tag_ids.0"]} />
                </div>
              ) : null}

              <div className="border-border/70 bg-muted/20 rounded-xl border px-4 py-4 text-sm">
                <div className="flex items-start gap-3">
                  <Sparkles className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                  <div className="space-y-1">
                    <p className="font-medium">
                      Manual recipient picking is not in this slice yet.
                    </p>
                    <p className="text-muted-foreground">
                      Drafts support sending to all contacts or to a tag-based segment for now.
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="border-border/70 border shadow-sm">
            <CardHeader>
              <CardTitle>Audience preview</CardTitle>
              <CardDescription>
                Preview the active contacts who currently match this draft audience.
              </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-4">
              <div className="bg-muted/30 rounded-2xl px-4 py-4">
                <p className="text-muted-foreground text-xs tracking-[0.18em] uppercase">
                  Matching recipients
                </p>
                <div className="mt-2 flex items-end justify-between gap-4">
                  <div>
                    <p className="text-3xl font-semibold">{audiencePreview.count}</p>
                    <p className="text-muted-foreground mt-1 text-sm">
                      {audiencePreview.implemented
                        ? audiencePreview.audience_label
                        : "Manual selection preview not available yet"}
                    </p>
                  </div>

                  {selectedAudienceType ? (
                    <Badge variant="outline" className="bg-background/80">
                      {selectedAudienceType.label}
                    </Badge>
                  ) : null}
                </div>
              </div>

              {previewIsStale ? (
                <p className="text-muted-foreground text-sm">
                  Audience settings changed. Refresh the preview to see the current count.
                </p>
              ) : (
                <p className="text-muted-foreground text-sm">
                  Only active contacts owned by your account are counted here.
                </p>
              )}

              {previewError ? <InputError message={previewError} /> : null}
            </CardContent>

            <CardFooter className="justify-between gap-3">
              <Link href={cancelHref} className={buttonVariants({ variant: "outline" })}>
                Cancel
              </Link>

              <div className="flex gap-3">
                <Button variant="outline" disabled={previewProcessing} onClick={refreshPreview}>
                  {previewProcessing ? <LoaderCircle className="animate-spin" /> : null}
                  Refresh preview
                </Button>
                <Button disabled={form.processing} onClick={submit}>
                  {submitLabel}
                </Button>
              </div>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}
