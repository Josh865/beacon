import { Head, Link } from "@inertiajs/react";
import { Megaphone, Plus, SquarePen } from "lucide-react";

import CampaignController from "@/actions/App/Http/Controllers/CampaignController";
import CampaignStatusBadge from "@/components/campaigns/campaign-status-badge";
import Heading from "@/components/heading";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { create as createCampaign, index as campaignsIndex } from "@/routes/campaigns";

type Campaign = {
  id: number;
  name: string;
  status: "draft" | "scheduled" | "processing" | "sent" | "cancelled";
  status_label: string;
  audience_type: "all_contacts" | "tag_selection" | "manual_selection";
  audience_label: string;
  scheduled_for: string | null;
  recipient_count: number;
  delivery_counts: {
    pending: number;
    sent: number;
    failed: number;
  };
  updated_at: string;
  can_edit: boolean;
  tags: Array<{
    id: number;
    name: string;
    slug: string;
  }>;
};

type CampaignsPageProps = {
  campaigns: {
    data: Campaign[];
  };
};

export default function CampaignsIndex({ campaigns }: CampaignsPageProps) {
  return (
    <>
      <Head title="Campaigns" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <Heading
            title="Campaigns"
            description="Draft thoughtful church messages, preview the audience, and keep every message scoped to your workspace."
            variant="small"
          />

          <Link href={createCampaign()} prefetch className={buttonVariants({ size: "lg" })}>
            <Plus />
            New campaign
          </Link>
        </div>

        <Card className="border-border/70 border shadow-sm">
          <CardHeader>
            <CardTitle>Drafts and recent campaigns</CardTitle>
            <CardDescription>
              Track drafts, scheduled sends, and delivery progress without leaving your own
              workspace.
            </CardDescription>
          </CardHeader>

          <CardContent className="px-0">
            {campaigns.data.length === 0 ? (
              <div className="flex flex-col items-center justify-center gap-3 px-6 py-18 text-center">
                <div className="bg-muted rounded-full p-3">
                  <Megaphone className="text-muted-foreground size-5" />
                </div>
                <div className="space-y-1">
                  <h3 className="text-base font-semibold">No campaigns yet</h3>
                  <p className="text-muted-foreground max-w-md text-sm">
                    Create your first draft to start shaping a message before schedule and send
                    flows are added.
                  </p>
                </div>
                <Link href={createCampaign()} className={buttonVariants({ variant: "outline" })}>
                  Create draft
                </Link>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Campaign</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Audience</TableHead>
                    <TableHead>Recipients</TableHead>
                    <TableHead>Tags</TableHead>
                    <TableHead>Updated</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {campaigns.data.map((campaign) => (
                    <TableRow key={campaign.id}>
                      <TableCell>
                        <div className="flex flex-col gap-1">
                          <Link
                            href={CampaignController.show(campaign.id)}
                            className="font-medium hover:underline"
                            prefetch
                          >
                            {campaign.name}
                          </Link>
                          <p className="text-muted-foreground text-sm">{campaign.audience_label}</p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <CampaignStatusBadge status={campaign.status} />
                      </TableCell>
                      <TableCell>
                        <span className="text-sm">{campaign.audience_label}</span>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col gap-1 text-sm">
                          <div className="font-medium">{campaign.recipient_count} snapshotted</div>
                          <p className="text-muted-foreground">
                            Sent {campaign.delivery_counts.sent} • Failed{" "}
                            {campaign.delivery_counts.failed} • Pending{" "}
                            {campaign.delivery_counts.pending}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex max-w-sm flex-wrap gap-1.5">
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
                      </TableCell>
                      <TableCell>{new Date(campaign.updated_at).toLocaleString()}</TableCell>
                      <TableCell>
                        <Link
                          href={
                            campaign.can_edit
                              ? CampaignController.edit(campaign.id)
                              : CampaignController.show(campaign.id)
                          }
                          prefetch
                          className={buttonVariants({ variant: "outline", size: "sm" })}
                        >
                          <SquarePen />
                          {campaign.can_edit ? "Edit" : "View"}
                        </Link>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

CampaignsIndex.layout = {
  breadcrumbs: [
    {
      title: "Campaigns",
      href: campaignsIndex(),
    },
  ],
};
