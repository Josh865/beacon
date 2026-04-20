import { Head, Link, router } from "@inertiajs/react";
import { Plus, Search, SquarePen } from "lucide-react";
import { useState } from "react";

import ContactController from "@/actions/App/Http/Controllers/ContactController";
import AppPagination, { type PaginationData } from "@/components/app-pagination";
import ContactStatusBadge from "@/components/contacts/contact-status-badge";
import Heading from "@/components/heading";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { create as createContact, index as contactsIndex } from "@/routes/contacts";

type Tag = {
  id: number;
  name: string;
  slug: string;
};

type Contact = {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string;
  status: "active" | "inactive";
  notes: string | null;
  tags: Tag[];
};

type StatusOption = {
  value: string;
  label: string;
};

type FilterState = {
  search: string;
  status: string;
  tag: number | null;
};

type ContactsPageProps = {
  contacts: PaginationData & {
    data: Contact[];
  };
  filters: FilterState;
  tags: Tag[];
  statusOptions: StatusOption[];
};

export default function ContactsIndex({
  contacts: paginatedContacts,
  filters,
  tags,
  statusOptions,
}: ContactsPageProps) {
  const [search, setSearch] = useState(filters.search);
  const [status, setStatus] = useState(filters.status || "all");
  const [tag, setTag] = useState<string>(filters.tag ? String(filters.tag) : "all");

  const applyFilters = () => {
    router.get(
      contactsIndex({
        query: {
          search: search || undefined,
          status: status === "all" ? undefined : status,
          tag: tag === "all" ? undefined : tag,
        },
      }),
      {},
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    );
  };

  const clearFilters = () => {
    setSearch("");
    setStatus("all");
    setTag("all");

    router.get(contactsIndex(), {}, { preserveScroll: true, replace: true });
  };

  return (
    <>
      <Head title="Contacts" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <Heading
            title="Contacts"
            description="Manage church directory records, outreach segments, and ministry follow-up tags."
            variant="small"
          />

          <Link href={createContact()} className={cn(buttonVariants({ size: "lg" }))} prefetch>
            <Plus />
            New contact
          </Link>
        </div>

        <Card className="border-border/70 border shadow-sm">
          <CardHeader className="gap-4">
            <div>
              <CardTitle>Directory</CardTitle>
              <CardDescription>
                Search across names, email addresses, phone numbers, and ministry tags.
              </CardDescription>
            </div>

            <div className="grid gap-3 lg:grid-cols-[minmax(0,1.6fr)_220px_220px_auto_auto]">
              <div className="relative">
                <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter") {
                      applyFilters();
                    }
                  }}
                  className="pl-9"
                  placeholder="Search name, email, or phone"
                />
              </div>

              <Select value={status} onValueChange={(value) => setStatus(value ?? "all")}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  {statusOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={tag} onValueChange={(value) => setTag(value ?? "all")}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All tags</SelectItem>
                  {tags.map((currentTag) => (
                    <SelectItem key={currentTag.id} value={String(currentTag.id)}>
                      {currentTag.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Button onClick={applyFilters}>Apply filters</Button>
              <Button variant="outline" onClick={clearFilters}>
                Clear
              </Button>
            </div>
          </CardHeader>

          <CardContent className="px-0">
            {paginatedContacts.data.length === 0 ? (
              <div className="flex flex-col items-center justify-center gap-3 px-6 py-18 text-center">
                <div className="bg-muted rounded-full p-3">
                  <Search className="text-muted-foreground size-5" />
                </div>
                <div className="space-y-1">
                  <h3 className="text-base font-semibold">No contacts found</h3>
                  <p className="text-muted-foreground max-w-md text-sm">
                    Try widening your filters or create a new record to start building your church
                    messaging list.
                  </p>
                </div>
                <Link href={createContact()} className={buttonVariants({ variant: "outline" })}>
                  Create a contact
                </Link>
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Contact</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Tags</TableHead>
                      <TableHead>Notes</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {paginatedContacts.data.map((contact) => (
                      <TableRow key={contact.id}>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <div className="font-medium">{contact.full_name}</div>
                            <div className="text-muted-foreground text-sm">
                              {contact.email ?? "No email provided"}
                            </div>
                            <div className="text-muted-foreground text-sm">{contact.phone}</div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <ContactStatusBadge status={contact.status} />
                        </TableCell>
                        <TableCell>
                          <div className="flex max-w-sm flex-wrap gap-1.5">
                            {contact.tags.length > 0 ? (
                              contact.tags.map((tagItem) => (
                                <Badge key={tagItem.id} variant="outline" className="bg-muted/30">
                                  {tagItem.name}
                                </Badge>
                              ))
                            ) : (
                              <span className="text-muted-foreground text-sm">
                                No tags assigned
                              </span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <p className="text-muted-foreground max-w-sm truncate text-sm">
                            {contact.notes ?? "No notes added yet."}
                          </p>
                        </TableCell>
                        <TableCell>
                          <Link
                            href={ContactController.edit(contact.id)}
                            className={buttonVariants({ size: "sm", variant: "ghost" })}
                            prefetch
                          >
                            <SquarePen />
                            Edit
                          </Link>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                <AppPagination {...paginatedContacts} itemLabel="contacts" />
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

ContactsIndex.layout = {
  breadcrumbs: [
    {
      title: "Contacts",
      href: contactsIndex(),
    },
  ],
};
