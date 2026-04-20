import { Head } from "@inertiajs/react";

import CampaignController from "@/actions/App/Http/Controllers/CampaignController";
import CampaignForm, {
  type CampaignAudiencePreview,
  type CampaignFormValues,
} from "@/components/campaigns/campaign-form";
import { create as createCampaign, index as campaignsIndex } from "@/routes/campaigns";

type Tag = {
  id: number;
  name: string;
  slug: string;
};

type AudienceTypeOption = {
  value: "all_contacts" | "tag_selection" | "manual_selection";
  label: string;
  implemented: boolean;
};

type CreateCampaignProps = {
  campaign: CampaignFormValues;
  tags: Tag[];
  audienceTypes: AudienceTypeOption[];
  audiencePreview: CampaignAudiencePreview;
};

export default function CreateCampaign({
  campaign,
  tags,
  audienceTypes,
  audiencePreview,
}: CreateCampaignProps) {
  return (
    <>
      <Head title="Create Campaign" />

      <CampaignForm
        title="Create campaign"
        description="Start a new message draft for your church communications dashboard."
        campaign={campaign}
        tags={tags}
        audienceTypes={audienceTypes}
        audiencePreview={audiencePreview}
        submitLabel="Create draft"
        cancelHref={campaignsIndex().url}
        submitAction={CampaignController.store()}
      />
    </>
  );
}

CreateCampaign.layout = {
  breadcrumbs: [
    {
      title: "Campaigns",
      href: campaignsIndex(),
    },
    {
      title: "Create",
      href: createCampaign(),
    },
  ],
};
