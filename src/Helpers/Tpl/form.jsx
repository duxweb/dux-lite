import React from 'react'
import {useRouter, ModalForm} from 'duxweb'
import {Input, Form as ArcoForm} from '@arco-design/web-react'

export default function Form() {
  const {params} = useRouter()

  return (
    <ModalForm url={`{{routeUrl}}/${params.id || 0}`}>
      {({ data }) => (
        <>
          <ArcoForm.Item label='名称' field='name' required>
            <Input placeholder='请输入名称'/>
          </ArcoForm.Item>
        </>
      )}
    </ModalForm>
  )
}
