import { useBlockProps } from "@wordpress/block-editor";
import { TextareaControl } from "@wordpress/components";

const Edit = ({ attributes, setAttributes }) => {
    return (
        <div {...useBlockProps()}>
            <TextareaControl
                label="Enter Markdown"
                value={attributes.content}
                onChange={(value) => setAttributes({ content: value })}
            />
        </div>
    );
};

export default Edit;
