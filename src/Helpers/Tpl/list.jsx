import React, {useRef, useMemo} from 'react'
import {LinkConfirm, LinkModal, PageTable, UrlSwitch} from 'duxweb'
import {IconPlus} from '@arco-design/web-react/icon';

export default function Table() {
  const table = useRef(null)

  const columns = useMemo(() => {
    return [
      {
        dataIndex: 'name',
        title: '名称'
      },
      {
        dataIndex: 'status',
        title: '状态',
        render: (_, record) => (
          <UrlSwitch url={`{{routeUrl}}/${record.id}/store`} field='status' defaultChecked={!!record.status}/>)
      },
      {
        dataIndex: 'op',
        title: '操作',
        width: 180,
        fixed: 'right',
        render: (_, record) => (
          <>
            <LinkModal
              url='{{pageUrl}}'
              params={{
                id: record.id
              }}
              title='编辑页面'
              name='编辑'
              table={table}
              button={{
                size: 'small',
                type: 'text'
              }}
              permission='{{name}}.edit'
            />
            <LinkConfirm
              url={`{{routeUrl}}/${record.id}`}
              title='确认进行删除？'
              name='删除'
              table={table}
              button={{
                size: 'small',
                type: 'text',
                status: 'danger'
              }}
              permission='{{name}}.del'
            />
          </>
        )
      }
    ]
  }, [])

  return (
    <PageTable
      ref={table}
      title='列表管理'
      url='{{routeUrl}}'
      primaryKey='id'
      columns={columns}
      permission='{{name}}.list'
      menus={<>
        <LinkModal
          url='{{pageUrl}}'
          title='添加页面'
          name='新建'
          table={table}
          button={{
            type: 'primary',
            icon: <IconPlus />
          }}
          permission='{{name}}.add'
        ></LinkModal>
      </>}
    ></PageTable>
  )
}
