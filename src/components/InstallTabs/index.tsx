import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';
import CodeBlock from '@theme/CodeBlock';
import {JSX} from 'react';

type Props = {
  /** Source repository URL */
  repo?: string;
};

export default function InstallTabs({
  repo = 'https://github.com/php/docbook-cs.git',
}: Props): JSX.Element {
  return (
    <Tabs groupId="install-method" queryString>
      <TabItem value="git" label="Git" default>
        <CodeBlock language="bash">{`git clone ${repo}
cd docbook-cs`}</CodeBlock>
      </TabItem>
    </Tabs>
  );
}
