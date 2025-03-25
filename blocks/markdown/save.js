import { useBlockProps } from "@wordpress/block-editor";

const Save = ({ attributes }) => {
    return (
        <div {...useBlockProps()}>
            <div className="markdown-content">{attributes.content}</div>
        </div>
    );
};

export default Save;
