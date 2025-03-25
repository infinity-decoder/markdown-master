import { registerBlockType } from "@wordpress/blocks";
import Edit from "./edit";
import Save from "./save";

registerBlockType("markdown-master/markdown", {
    apiVersion: 2,
    title: "Markdown Block",
    icon: "editor-code",
    category: "widgets",
    attributes: {
        content: {
            type: "string",
            source: "text",
            selector: ".markdown-content",
        },
    },
    edit: Edit,
    save: Save,
});
