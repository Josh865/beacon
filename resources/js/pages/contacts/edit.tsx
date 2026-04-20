import { Head } from "@inertiajs/react";

import ContactController from "@/actions/App/Http/Controllers/ContactController";
import ContactForm, { type ContactFormValues } from "@/components/contacts/contact-form";
import { index as contactsIndex } from "@/routes/contacts";

type Tag = {
  id: number;
  name: string;
  slug: string;
};

type StatusOption = {
  value: "active" | "inactive";
  label: string;
};

type EditContactFormValues = ContactFormValues & {
  id: number;
};

export default function EditContact({
  contact,
  tags,
  statusOptions,
}: {
  contact: EditContactFormValues;
  tags: Tag[];
  statusOptions: StatusOption[];
}) {
  return (
    <>
      <Head title={`Edit ${contact.first_name} ${contact.last_name}`} />

      <ContactForm
        title="Edit contact"
        description="Update status, communication details, and segment tags for this contact."
        contact={contact}
        tags={tags}
        statusOptions={statusOptions}
        submitLabel="Save changes"
        submitAction={ContactController.update(contact.id)}
      />
    </>
  );
}

EditContact.layout = {
  breadcrumbs: [
    {
      title: "Contacts",
      href: contactsIndex(),
    },
  ],
};
