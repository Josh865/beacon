import type { UrlMethodPair } from "@inertiajs/core";
import { Link, useForm } from "@inertiajs/react";

import Heading from "@/components/heading";
import InputError from "@/components/input-error";
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
import { index as contactsIndex } from "@/routes/contacts";

type TagOption = {
  id: number;
  name: string;
  slug: string;
};

type StatusOption = {
  value: "active" | "inactive";
  label: string;
};

export type ContactFormValues = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  status: "active" | "inactive";
  notes: string;
  tag_ids: number[];
};

type ContactFormProps = {
  title: string;
  description: string;
  contact: ContactFormValues;
  tags: TagOption[];
  statusOptions: StatusOption[];
  submitLabel: string;
  submitAction: UrlMethodPair;
};

export default function ContactForm({
  title,
  description,
  contact,
  tags,
  statusOptions,
  submitLabel,
  submitAction,
}: ContactFormProps) {
  const form = useForm<ContactFormValues>(contact);

  const toggleTag = (tagId: number, checked: boolean) => {
    form.setData(
      "tag_ids",
      checked
        ? [...form.data.tag_ids, tagId].sort((left, right) => left - right)
        : form.data.tag_ids.filter((currentTagId) => currentTagId !== tagId),
    );
  };

  const submit = () => {
    form.submit(submitAction, {
      preserveScroll: true,
    });
  };

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
      <Heading title={title} description={description} />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <Card className="border-border/70 border shadow-sm">
          <CardHeader>
            <CardTitle>Contact details</CardTitle>
            <CardDescription>
              Capture the basics your communications team uses every week.
            </CardDescription>
          </CardHeader>

          <CardContent className="grid gap-5">
            <div className="grid gap-5 md:grid-cols-2">
              <div className="grid gap-2">
                <Label htmlFor="first_name">First name</Label>
                <Input
                  id="first_name"
                  value={form.data.first_name}
                  onChange={(event) => form.setData("first_name", event.target.value)}
                  autoComplete="given-name"
                  placeholder="Ava"
                />
                <InputError message={form.errors.first_name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="last_name">Last name</Label>
                <Input
                  id="last_name"
                  value={form.data.last_name}
                  onChange={(event) => form.setData("last_name", event.target.value)}
                  autoComplete="family-name"
                  placeholder="Thompson"
                />
                <InputError message={form.errors.last_name} />
              </div>
            </div>

            <div className="grid gap-5 md:grid-cols-2">
              <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>
                <Input
                  id="email"
                  type="email"
                  value={form.data.email}
                  onChange={(event) => form.setData("email", event.target.value)}
                  autoComplete="email"
                  placeholder="ava@example.com"
                />
                <InputError message={form.errors.email} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                  id="phone"
                  value={form.data.phone}
                  onChange={(event) => form.setData("phone", event.target.value)}
                  autoComplete="tel"
                  placeholder="317-555-0108"
                />
                <InputError message={form.errors.phone} />
              </div>
            </div>

            <div className="grid gap-2">
              <Label htmlFor="status">Status</Label>
              <Select
                value={form.data.status}
                onValueChange={(value) => form.setData("status", value as "active" | "inactive")}
              >
                <SelectTrigger id="status" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statusOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={form.errors.status} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="notes">Notes</Label>
              <textarea
                id="notes"
                value={form.data.notes}
                onChange={(event) => form.setData("notes", event.target.value)}
                rows={5}
                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 min-h-32 rounded-xl border bg-transparent px-3 py-2 text-sm transition-colors outline-none focus-visible:ring-3"
                placeholder="Example: prefers text messages, serves on first Sunday, family in youth ministry."
              />
              <InputError message={form.errors.notes} />
            </div>
          </CardContent>
        </Card>

        <Card className="border-border/70 from-muted/20 to-background border bg-linear-to-b shadow-sm">
          <CardHeader>
            <CardTitle>Tags</CardTitle>
            <CardDescription>
              Use tags to segment communication lists and ministry follow-up.
            </CardDescription>
          </CardHeader>

          <CardContent className="grid gap-3">
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

            <InputError message={form.errors.tag_ids} />
          </CardContent>

          <CardFooter className="justify-between gap-3">
            <Link href={contactsIndex()} className={buttonVariants({ variant: "outline" })}>
              Cancel
            </Link>
            <Button disabled={form.processing} onClick={submit}>
              {submitLabel}
            </Button>
          </CardFooter>
        </Card>
      </div>
    </div>
  );
}
